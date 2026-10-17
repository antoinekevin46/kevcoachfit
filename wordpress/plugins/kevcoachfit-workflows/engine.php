<?php
defined( 'ABSPATH' ) || exit;

function kcf_install_workflows() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE {$wpdb->prefix}kcf_sessions (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        series varchar(80) NOT NULL,
        title varchar(160) NOT NULL,
        venue varchar(160) NOT NULL,
        starts_at datetime NOT NULL,
        duration smallint unsigned NOT NULL,
        capacity smallint unsigned NOT NULL DEFAULT 15,
        PRIMARY KEY  (id),
        UNIQUE KEY occurrence (series,starts_at)
    ) ENGINE=InnoDB $charset;" );
    dbDelta( "CREATE TABLE {$wpdb->prefix}kcf_bookings (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        session_id bigint unsigned NOT NULL,
        name varchar(160) NOT NULL,
        email varchar(254) NOT NULL,
        status varchar(24) NOT NULL,
        key_hash varchar(64) NOT NULL,
        offered_until datetime NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY queue (session_id,status,id)
    ) ENGINE=InnoDB $charset;" );
    dbDelta( "CREATE TABLE {$wpdb->prefix}kcf_requests (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        kind varchar(20) NOT NULL,
        seller varchar(20) NOT NULL,
        name varchar(160) NOT NULL,
        email varchar(254) NOT NULL,
        phone varchar(40) NOT NULL,
        details longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=InnoDB $charset;" );
    add_option( 'kcf_products', kcf_default_products(), '', false );
    update_option( 'kcf_schema_version', '1' );
    $admin=get_role('administrator');
    foreach(array('kcf_access','kcf_view_kevin','kcf_view_yohan') as $cap){$admin->add_cap($cap);}
    add_role('kcf_seller_kevin','Producteur — Kévin',array('read'=>true,'kcf_access'=>true,'kcf_view_kevin'=>true));
    add_role('kcf_seller_yohan','Producteur — Yohan',array('read'=>true,'kcf_access'=>true,'kcf_view_yohan'=>true));
}
function kcf_error( $code, $message, $status = 400 ) {
    return new WP_Error( $code, $message, array( 'status' => $status ) );
}
function kcf_customer( $input ) {
    $name = sanitize_text_field( is_string($input['name']??null)?$input['name']:'' );
    $email = sanitize_email( is_string($input['email']??null)?$input['email']:'' );
    if ( strlen( $name ) < 2 || strlen( $name ) > 160 || ! is_email( $email ) || strlen( $email ) > 254 ) {
        return kcf_error( 'customer', 'Indiquez votre nom et une adresse e-mail valide.' );
    }
    return array( 'name' => $name, 'email' => strtolower( $email ) );
}
function kcf_timezone() { return new DateTimeZone('Europe/Paris'); }
function kcf_utc( $timestamp ) { return gmdate( 'Y-m-d H:i:s', $timestamp ); }
function kcf_timestamp( $utc ) { return strtotime( $utc . ' UTC' ); }
function kcf_collective_closed($utc) {
    $local=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(kcf_timezone());
    return in_array((int)$local->format('n'),array(7,8),true);
}

function kcf_session_lock( $id ) {
    global $wpdb;
    $wpdb->query( 'START TRANSACTION' );
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kcf_sessions WHERE id=%d FOR UPDATE", $id ) );
}
function kcf_occupancy( $session_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}kcf_bookings WHERE session_id=%d AND status IN ('reserved','offered')", $session_id ) );
}
// Must run while holding the session row lock. A waitlist offer occupies a seat.
function kcf_refresh_locked( $session, $now ) {
    global $wpdb;
    $table = $wpdb->prefix . 'kcf_bookings';
    $wpdb->query( $wpdb->prepare( "UPDATE $table SET status='expired' WHERE session_id=%d AND status='offered' AND offered_until<=%s", $session->id, kcf_utc( $now ) ) );
    if(kcf_collective_closed($session->starts_at)){return;}
    // Offer for at most two hours, always ending by the start of the class.
    if ( kcf_timestamp( $session->starts_at ) <= $now ) { return; }
    $free = max( 0, $session->capacity - kcf_occupancy( $session->id ) );
    if ( ! $free ) { return; }
    $waiting = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE session_id=%d AND status='waiting' ORDER BY id ASC LIMIT %d", $session->id, $free ) );
    foreach ( $waiting as $id ) {
        $wpdb->update( $table, array( 'status' => 'offered', 'offered_until' => kcf_utc( min($now + 2 * HOUR_IN_SECONDS, kcf_timestamp($session->starts_at)) ) ), array( 'id' => $id ) );
    }
}
function kcf_book_session( $session_id, $input, $now = null ) {
    global $wpdb;
    $now = $now ?? time();
    $customer = kcf_customer( $input );
    if ( is_wp_error( $customer ) ) { return $customer; }
    $session = kcf_session_lock( $session_id );
    if ( ! $session || kcf_timestamp( $session->starts_at ) <= $now ) {
        $wpdb->query( 'ROLLBACK' ); return kcf_error( 'session', 'Ce cours n’est plus disponible.' );
    }
    if(kcf_collective_closed($session->starts_at)){$wpdb->query('ROLLBACK');return kcf_error('closure','Les cours collectifs sont fermés en juillet et en août.',409);}
    kcf_refresh_locked( $session, $now );
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}kcf_bookings WHERE session_id=%d AND email=%s AND status IN ('reserved','waiting','offered')", $session_id, $customer['email'] ) );
    if ( $existing ) {
        $wpdb->query( 'COMMIT' ); return kcf_error( 'duplicate', 'Vous avez déjà une inscription ou une demande pour ce cours.', 409 );
    }
    $waiting = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}kcf_bookings WHERE session_id=%d AND status='waiting'", $session_id ) );
    $status = kcf_occupancy( $session_id ) < $session->capacity && ! $waiting ? 'reserved' : 'waiting';
    $token = bin2hex( random_bytes( 32 ) );
    $ok = $wpdb->insert( $wpdb->prefix . 'kcf_bookings', array(
        'session_id' => $session_id, 'name' => $customer['name'], 'email' => $customer['email'],
        'status' => $status, 'key_hash' => hash( 'sha256', $token ), 'created_at' => kcf_utc( $now ),
    ) );
    if ( ! $ok ) { $wpdb->query( 'ROLLBACK' ); return kcf_error( 'storage', 'L’inscription n’a pas été enregistrée. Réessayez.', 500 ); }
    $id = (int) $wpdb->insert_id;
    $wpdb->query( 'COMMIT' );
    $management=home_url('/mes-reservations/');
    if(current_user_can('manage_options')&&function_exists('kcf_workshop_url')&&kcf_workshop_url('mes-reservations')){$management=kcf_workshop_url('mes-reservations');}
    return array( 'id' => $id, 'status' => $status,
        'management_url' => $management.'#'.$id.'.'.$token,
        'message' => 'reserved' === $status ? 'Place réservée dans la démonstration. Conservez votre lien de gestion.' : 'Inscription en liste d’attente dans la démonstration. Conservez votre lien de suivi.',
        'demo' => true,
    );
}
function kcf_manage_booking( $id, $token, $operation = 'status', $now = null ) {
    global $wpdb;
    $now = $now ?? time();
    if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) { return kcf_error( 'access', 'Lien de réservation invalide.', 403 ); }
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kcf_bookings WHERE id=%d", $id ) );
    if ( ! $booking || ! hash_equals( $booking->key_hash, hash( 'sha256', $token ) ) ) {
        return kcf_error( 'access', 'Lien de réservation invalide.', 403 );
    }
    $session = kcf_session_lock( $booking->session_id );
    if ( ! $session ) { $wpdb->query( 'ROLLBACK' ); return kcf_error( 'session', 'Cours introuvable.' ); }
    kcf_refresh_locked( $session, $now );
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kcf_bookings WHERE id=%d FOR UPDATE", $id ) );
    if ( 'cancel' === $operation ) {
        if ( 'reserved' === $booking->status && $now > kcf_timestamp( $session->starts_at ) - 2 * HOUR_IN_SECONDS ) {
            $wpdb->query( 'COMMIT' ); return kcf_error( 'cutoff', 'L’annulation en ligne ferme deux heures avant le cours.', 409 );
        }
        $wpdb->update( $wpdb->prefix . 'kcf_bookings', array( 'status' => 'cancelled' ), array( 'id' => $id ) );
        kcf_refresh_locked( $session, $now );
    } elseif ( 'confirm' === $operation ) {
        if(kcf_collective_closed($session->starts_at)){$wpdb->query('COMMIT');return kcf_error('closure','Les cours collectifs sont fermés en juillet et en août.',409);}
        if ( 'offered' !== $booking->status || kcf_timestamp( $booking->offered_until ) <= $now ) {
            $wpdb->query( 'COMMIT' ); return kcf_error( 'offer', 'Aucune proposition de place valide à confirmer.', 409 );
        }
        $wpdb->update( $wpdb->prefix . 'kcf_bookings', array( 'status' => 'reserved', 'offered_until' => null ), array( 'id' => $id ) );
    } elseif ( 'status' !== $operation ) {
        $wpdb->query( 'ROLLBACK' ); return kcf_error( 'operation', 'Action inconnue.' );
    }
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT status,offered_until FROM {$wpdb->prefix}kcf_bookings WHERE id=%d", $id ) );
    $wpdb->query( 'COMMIT' );
    return array( 'status' => $booking->status, 'offered_until' => $booking->offered_until ? gmdate( 'c', kcf_timestamp( $booking->offered_until ) ) : null,
        'title' => $session->title, 'venue' => $session->venue, 'starts_at' => gmdate( 'c', kcf_timestamp( $session->starts_at ) ), 'demo' => true );
}
function kcf_default_products() {
    return array(
        'fleurs-500' => array( 'seller'=>'kevin', 'name'=>'Miel toutes fleurs — 500 g', 'price'=>750, 'example'=>true ),
        'fleurs-1000'=> array( 'seller'=>'kevin', 'name'=>'Miel toutes fleurs — 1 kg', 'price'=>1400, 'example'=>true ),
        'foret-500' => array( 'seller'=>'kevin', 'name'=>'Miel de forêt — 500 g', 'price'=>900, 'example'=>true ),
        'foret-1000'=> array( 'seller'=>'kevin', 'name'=>'Miel de forêt — 1 kg', 'price'=>1700, 'example'=>true ),
        'vin' => array( 'seller'=>'yohan', 'name'=>'Du rouge aux lèvres', 'price'=>850, 'example'=>false ),
        'fraise'=> array( 'seller'=>'yohan', 'name'=>'Liqueur de fraise', 'price'=>1800, 'example'=>true ),
        'ratafia'=>array( 'seller'=>'yohan', 'name'=>'Ratafia', 'price'=>1600, 'example'=>true ),
    );
}
function kcf_products() { return get_option( 'kcf_products', kcf_default_products() ); }
function kcf_save_request( $input ) {
    global $wpdb;
    $customer = kcf_customer( $input );
    if ( is_wp_error( $customer ) ) { return $customer; }
    $phone = sanitize_text_field( is_string($input['phone']??null)?$input['phone']:'' );
    if ( strlen( $phone ) > 40 ) { return kcf_error( 'phone', 'Numéro de téléphone trop long.' ); }
    $kind = $input['kind'] ?? '';
    if ( 'order' === $kind ) {
        $seller = $input['seller'] ?? '';
        $products = kcf_products(); $lines = array(); $total = 0;
        if ( ! in_array( $seller, array( 'kevin', 'yohan' ), true ) ) { return kcf_error( 'seller', 'Choisissez un producteur.' ); }
        foreach ( (array) ( $input['items'] ?? array() ) as $key => $quantity ) {
            if ( ! is_numeric( $quantity ) || (float) $quantity !== (float) (int) $quantity || (int) $quantity < 0 || (int) $quantity > 24 ) { return kcf_error( 'quantity', 'Choisissez une quantité entière de 0 à 24.' ); }
            $quantity = (int) $quantity;
            if ( ! $quantity ) { continue; }
            if ( ! isset( $products[$key] ) || $products[$key]['seller'] !== $seller ) { return kcf_error( 'mixed', 'Les demandes doivent être séparées par producteur.' ); }
            $p = $products[$key]; $lines[] = array( 'product'=>$key, 'name'=>$p['name'], 'quantity'=>$quantity, 'unit_cents'=>$p['price'], 'example'=>$p['example'] );
            $total += $quantity * $p['price'];
        }
        if ( ! $lines ) { return kcf_error( 'empty', 'Sélectionnez au moins un produit.' ); }
        if ( 'yohan' === $seller && !in_array($input['adult']??null,array(true,1,'1'),true) ) { return kcf_error( 'adult', 'La demande de produits alcoolisés est réservée aux personnes majeures.' ); }
        $details = array( 'lines'=>$lines, 'total_cents'=>$total, 'demo'=>true );
    } elseif ( 'coaching' === $kind ) {
        $seller = 'kevin';
        $date = is_string($input['date']??null)?$input['date']:'';
        $at = is_string($input['time']??null)?$input['time']:'';
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^\d{2}:\d{2}$/',$at)){return kcf_error('appointment','Date ou heure invalide.');}
        $address = sanitize_textarea_field( $input['address'] ?? '' );
        $parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $at, kcf_timezone() );
        if ( ! $parsed || $parsed->format('Y-m-d H:i') !== $date.' '.$at || $parsed->getTimestamp() <= time() || strlen($address)<8 || strlen($address)>500 ) { return kcf_error( 'appointment', 'Choisissez une date future, une heure et une adresse complète.' ); }
        if ( 7 === (int) $parsed->format('N') || $at < '07:00' ) { return kcf_error( 'hours', 'Les demandes concernent le lundi au samedi, à partir de 7 h.' ); }
        $end = array( 1=>'17:00', 2=>'17:30', 4=>'18:00', 5=>'19:00', 6=>'16:00' )[(int)$parsed->format('N')] ?? null;
        if ( 3 === (int)$parsed->format('N') && $parsed->format('Y-m') >= '2027-01' ) { $end = '17:45'; }
        if ( $end && $parsed->modify('+60 minutes') > new DateTimeImmutable($date.' '.$end,kcf_timezone()) ) { return kcf_error( 'hours', 'La séance et l’échange doivent se terminer avant les cours ou la fin de journée.' ); }
        $details = array('preferred_date'=>$date, 'preferred_time'=>$at, 'address'=>$address, 'onsite_minutes'=>60, 'requires_travel_validation'=>true, 'requires_calendar_validation'=>true, 'demo'=>true);
    } else { return kcf_error( 'kind', 'Type de demande inconnu.' ); }
    $note = sanitize_textarea_field( $input['note'] ?? '' );
    if ( strlen($note)>2000 ) { return kcf_error( 'note', 'Votre message est trop long (2 000 caractères maximum).' ); }
    $details['note'] = $note;
    $ok = $wpdb->insert( $wpdb->prefix . 'kcf_requests', array( 'kind'=>$kind, 'seller'=>$seller, 'name'=>$customer['name'], 'email'=>$customer['email'], 'phone'=>$phone, 'details'=>wp_json_encode($details), 'created_at'=>kcf_utc(time()) ) );
    if ( ! $ok ) { return kcf_error('storage','Demande non enregistrée. Réessayez.',500); }
    return array('reference'=>'ESSAI-'.(int)$wpdb->insert_id, 'demo'=>true, 'message'=>'Demande d’essai enregistrée dans l’atelier. Aucun paiement ni notification n’a été envoyé.');
}
function kcf_seed_sessions() {
    global $wpdb;
    $series = array(
        array('seniors-cales','Vitali’Seniors',1,'17:00',45,'Calès · salle des fêtes'),
        array('seniors-payrac','Vitali’Seniors',2,'17:30',45,'Payrac · salle polyvalente'),
        array('training','Vitali’Training',2,'18:30',60,'Payrac · salle polyvalente'),
        array('renfort-cales','Vitali’Renfort',4,'18:00',45,'Calès · salle des fêtes'),
        array('renfort-payrac','Vitali’Renfort',4,'19:05',45,'Payrac · salle polyvalente'),
    );
    $today = new DateTimeImmutable('today', kcf_timezone());
    for ($day=0;$day<42;$day++) {
        $date=$today->modify('+'.$day.' days');
        if(kcf_collective_closed(kcf_utc($date->getTimestamp()))){continue;}
        foreach($series as $s) {
            if((int)$date->format('N')!==$s[2]) {continue;}
            $at=new DateTimeImmutable($date->format('Y-m-d').' '.$s[3],kcf_timezone());
            if($at->getTimestamp()<=time()) {continue;}
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}kcf_sessions (series,title,venue,starts_at,duration,capacity) VALUES (%s,%s,%s,%s,%d,15)",$s[0],$s[1],$s[5],kcf_utc($at->getTimestamp()),$s[4]));
        }
    }
}

<?php
defined( 'ABSPATH' ) || exit;

function kcfr_install_workflows() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE {$wpdb->prefix}kcfr_sessions (
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
    dbDelta( "CREATE TABLE {$wpdb->prefix}kcfr_bookings (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        session_id bigint unsigned NOT NULL,
        name varchar(160) NOT NULL,
        email varchar(254) NOT NULL,
        status varchar(24) NOT NULL,
        key_hash varchar(64) NOT NULL,
        key_ciphertext longtext NULL,
        offered_until datetime NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY queue (session_id,status,id)
    ) ENGINE=InnoDB $charset;" );
    dbDelta( "CREATE TABLE {$wpdb->prefix}kcfr_requests (
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

    update_option( 'kcfr_schema_version', '1' );

}
function kcfr_error( $code, $message, $status = 400 ) {
    return new WP_Error( $code, $message, array( 'status' => $status ) );
}
function kcfr_customer( $input ) {
    $name = sanitize_text_field( is_string($input['name']??null)?$input['name']:'' );
    $email = sanitize_email( is_string($input['email']??null)?$input['email']:'' );
    if ( strlen( $name ) < 2 || strlen( $name ) > 160 || ! is_email( $email ) || strlen( $email ) > 254 ) {
        return kcfr_error( 'customer', 'Indiquez votre nom et une adresse e-mail valide.' );
    }
    return array( 'name' => $name, 'email' => strtolower( $email ) );
}
function kcfr_timezone() { return new DateTimeZone('Europe/Paris'); }
function kcfr_utc( $timestamp ) { return gmdate( 'Y-m-d H:i:s', $timestamp ); }
function kcfr_timestamp( $utc ) { return strtotime( $utc . ' UTC' ); }
function kcfr_collective_closed($utc) {
    $local=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(kcfr_timezone());
    return in_array((int)$local->format('n'),array(7,8),true)||in_array($local->format('Y-m-d'),kcfr_settings()['closed_dates'],true);
}

function kcfr_session_lock( $id ) {
    global $wpdb;
    $wpdb->query( 'START TRANSACTION' );
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kcfr_sessions WHERE id=%d FOR UPDATE", $id ) );
}
function kcfr_occupancy( $session_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_bookings WHERE session_id=%d AND status IN ('reserved','offered')", $session_id ) );
}
// Must run while holding the session row lock. A waitlist offer occupies a seat.
function kcfr_refresh_locked( $session, $now ) {
    global $wpdb;
    $table = $wpdb->prefix . 'kcfr_bookings';
    $wpdb->query( $wpdb->prepare( "UPDATE $table SET status='expired' WHERE session_id=%d AND status='offered' AND offered_until<=%s", $session->id, kcfr_utc( $now ) ) );
    if(kcfr_collective_closed($session->starts_at)){return;}
    // Offer for at most two hours, always ending by the start of the class.
    if ( kcfr_timestamp( $session->starts_at ) <= $now ) { return; }
    $free = max( 0, $session->capacity - kcfr_occupancy( $session->id ) );
    if ( ! $free ) { return; }
    $waiting = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE session_id=%d AND status='waiting' ORDER BY id ASC LIMIT %d", $session->id, $free ) );
    foreach ( $waiting as $id ) {
        $wpdb->update( $table, array( 'status' => 'offered', 'offered_until' => kcfr_utc( min($now + 2 * HOUR_IN_SECONDS, kcfr_timestamp($session->starts_at)) ) ), array( 'id' => $id ) );
        kcfr_notice($id,'offered');
    }
}
function kcfr_book_session( $session_id, $input, $now = null ) {
    global $wpdb;
    $now = $now ?? time();
    $customer = kcfr_customer( $input );
    if ( is_wp_error( $customer ) ) { return $customer; }
    $session = kcfr_session_lock( $session_id );
    if ( ! $session || kcfr_timestamp( $session->starts_at ) <= $now ) {
        $wpdb->query( 'ROLLBACK' ); return kcfr_error( 'session', 'Ce cours n’est plus disponible.' );
    }
    if(kcfr_collective_closed($session->starts_at)){$wpdb->query('ROLLBACK');return kcfr_error('closure','Les cours collectifs sont fermés en juillet et en août.',409);}
    kcfr_refresh_locked( $session, $now );
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}kcfr_bookings WHERE session_id=%d AND email=%s AND status IN ('reserved','waiting','offered')", $session_id, $customer['email'] ) );
    if ( $existing ) {
        $wpdb->query( 'COMMIT' ); return kcfr_error( 'duplicate', 'Vous avez déjà une inscription ou une demande pour ce cours.', 409 );
    }
    $waiting = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_bookings WHERE session_id=%d AND status='waiting'", $session_id ) );
    $status = kcfr_occupancy( $session_id ) < $session->capacity && ! $waiting ? 'reserved' : 'waiting';
    $token = bin2hex( random_bytes( 32 ) );
    $ok = $wpdb->insert( $wpdb->prefix . 'kcfr_bookings', array(
        'session_id' => $session_id, 'name' => $customer['name'], 'email' => $customer['email'],
        'status' => $status, 'key_hash' => hash( 'sha256', $token ), 'key_ciphertext'=>kcfr_encrypt($token), 'created_at' => kcfr_utc( $now ),
    ) );
    if ( ! $ok ) { $wpdb->query( 'ROLLBACK' ); return kcfr_error( 'storage', 'L’inscription n’a pas été enregistrée. Réessayez.', 500 ); }
    $id = (int) $wpdb->insert_id;
    kcfr_notice($id,$status);
    $wpdb->query( 'COMMIT' );
    $management=kcfr_manage_url();
    return array( 'id' => $id, 'status' => $status,
        'management_url' => $management.'#'.$id.'.'.$token,
        'message' => 'reserved' === $status ? (kcfr_live()?'Votre place est réservée. Conservez ce lien pour gérer votre inscription.':'Test : place réservée, sans e-mail réel.') : (kcfr_live()?'Vous êtes sur la liste d’attente.':'Test : liste d’attente, sans e-mail réel.'),
        'demo' => !kcfr_live(),
    );
}
function kcfr_manage_booking( $id, $token, $operation = 'status', $now = null ) {
    global $wpdb;
    $now = $now ?? time();
    if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) { return kcfr_error( 'access', 'Lien de réservation invalide.', 403 ); }
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d", $id ) );
    if ( ! $booking || ! hash_equals( $booking->key_hash, hash( 'sha256', $token ) ) ) {
        return kcfr_error( 'access', 'Lien de réservation invalide.', 403 );
    }
    $session = kcfr_session_lock( $booking->session_id );
    if ( ! $session ) { $wpdb->query( 'ROLLBACK' ); return kcfr_error( 'session', 'Cours introuvable.' ); }
    kcfr_refresh_locked( $session, $now );
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d FOR UPDATE", $id ) );
    if ( 'cancel' === $operation ) {
        if ( 'reserved' === $booking->status && $now > kcfr_timestamp( $session->starts_at ) - 2 * HOUR_IN_SECONDS ) {
            $wpdb->query( 'COMMIT' ); return kcfr_error( 'cutoff', 'L’annulation en ligne ferme deux heures avant le cours.', 409 );
        }
        $wpdb->update( $wpdb->prefix . 'kcfr_bookings', array( 'status' => 'cancelled' ), array( 'id' => $id ) );
        kcfr_notice($id,'cancelled');
        kcfr_refresh_locked( $session, $now );
    } elseif ( 'confirm' === $operation ) {
        if(kcfr_collective_closed($session->starts_at)){$wpdb->query('COMMIT');return kcfr_error('closure','Les cours collectifs sont fermés en juillet et en août.',409);}
        if ( 'offered' !== $booking->status || kcfr_timestamp( $booking->offered_until ) <= $now ) {
            $wpdb->query( 'COMMIT' ); return kcfr_error( 'offer', 'Aucune proposition de place valide à confirmer.', 409 );
        }
        $wpdb->update( $wpdb->prefix . 'kcfr_bookings', array( 'status' => 'reserved', 'offered_until' => null ), array( 'id' => $id ) );
        kcfr_notice($id,'reserved');
    } elseif ( 'status' !== $operation ) {
        $wpdb->query( 'ROLLBACK' ); return kcfr_error( 'operation', 'Action inconnue.' );
    }
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT status,offered_until FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d", $id ) );
    $wpdb->query( 'COMMIT' );
    return array( 'status' => $booking->status, 'offered_until' => $booking->offered_until ? gmdate( 'c', kcfr_timestamp( $booking->offered_until ) ) : null,
        'title' => $session->title, 'venue' => $session->venue, 'starts_at' => gmdate( 'c', kcfr_timestamp( $session->starts_at ) ), 'demo' => !kcfr_live() );
}
function kcfr_save_request( $input ) {
    global $wpdb;
    $customer = kcfr_customer( $input );
    if ( is_wp_error( $customer ) ) { return $customer; }
    $phone = sanitize_text_field( is_string($input['phone']??null)?$input['phone']:'' );
    if ( strlen( $phone ) > 40 ) { return kcfr_error( 'phone', 'Numéro de téléphone trop long.' ); }
    $kind = $input['kind'] ?? '';
    if ( 'coaching' === $kind ) {
        $seller = 'kevin';
        $date = is_string($input['date']??null)?$input['date']:'';
        $at = is_string($input['time']??null)?$input['time']:'';
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^\d{2}:\d{2}$/',$at)){return kcfr_error('appointment','Date ou heure invalide.');}
        $address = sanitize_textarea_field( $input['address'] ?? '' );
        $parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $at, kcfr_timezone() );
        if ( ! $parsed || $parsed->format('Y-m-d H:i') !== $date.' '.$at || $parsed->getTimestamp() <= time() || strlen($address)<8 || strlen($address)>500 ) { return kcfr_error( 'appointment', 'Choisissez une date future, une heure et une adresse complète.' ); }
        if ( 7 === (int) $parsed->format('N') || $at < '07:00' ) { return kcfr_error( 'hours', 'Les demandes concernent le lundi au samedi, à partir de 7 h.' ); }
        $end = array( 1=>'17:00', 2=>'17:30', 4=>'18:00', 5=>'19:00', 6=>'16:00' )[(int)$parsed->format('N')] ?? null;
        if ( 3 === (int)$parsed->format('N') && $parsed->format('Y-m') >= '2027-01' ) { $end = '17:45'; }
        if ( $end && $parsed->modify('+60 minutes') > new DateTimeImmutable($date.' '.$end,kcfr_timezone()) ) { return kcfr_error( 'hours', 'La séance et l’échange doivent se terminer avant les cours ou la fin de journée.' ); }
        $onsite_end=$parsed->modify('+60 minutes');
        $conflicts=$wpdb->get_results($wpdb->prepare("SELECT starts_at FROM {$wpdb->prefix}kcfr_sessions WHERE starts_at<%s AND DATE_ADD(starts_at,INTERVAL duration MINUTE)>%s",kcfr_utc($onsite_end->getTimestamp()),kcfr_utc($parsed->getTimestamp())));
        foreach($conflicts as $conflict){if(!kcfr_collective_closed($conflict->starts_at)){return kcfr_error('busy','Ce créneau chevauche un cours. Choisissez un autre horaire.',409);}}
        $calendar_status='not_connected';
        if(get_option('kcfr_google_tokens')){
            $busy=kcfr_google_busy($parsed->getTimestamp(),$onsite_end->getTimestamp());
            if($busy===true){return kcfr_error('busy','Ce créneau est occupé dans l’agenda. Choisissez un autre horaire.',409);}
            $calendar_status=is_wp_error($busy)?'unavailable':'free_at_request';
        }
        $details = array('preferred_date'=>$date, 'preferred_time'=>$at, 'address'=>$address, 'onsite_minutes'=>60, 'requires_travel_validation'=>true, 'requires_calendar_validation'=>true,'calendar_status'=>$calendar_status, 'demo'=>!kcfr_live());
    } else { return kcfr_error( 'kind', 'Type de demande inconnu.' ); }
    $note = sanitize_textarea_field( $input['note'] ?? '' );
    if ( strlen($note)>2000 ) { return kcfr_error( 'note', 'Votre message est trop long (2 000 caractères maximum).' ); }
    $details['note'] = $note;
    $ok = $wpdb->insert( $wpdb->prefix . 'kcfr_requests', array( 'kind'=>$kind, 'seller'=>$seller, 'name'=>$customer['name'], 'email'=>$customer['email'], 'phone'=>$phone, 'details'=>wp_json_encode($details), 'created_at'=>kcfr_utc(time()) ) );
    if ( ! $ok ) { return kcfr_error('storage','Demande non enregistrée. Réessayez.',500); }
    if(kcfr_live()){kcfr_request_notice((int)$wpdb->insert_id);}
    return array('reference'=>'DEMANDE-'.(int)$wpdb->insert_id, 'demo'=>!kcfr_live(), 'message'=>kcfr_live()?'Votre demande est enregistrée. Kévin doit confirmer le créneau, son agenda et le trajet.':'Test : demande enregistrée sans envoi ni confirmation réelle.');
}
function kcfr_seed_sessions() {
    global $wpdb;
    $series = array(
        array('seniors-cales','Vitali’Seniors',1,'17:00',45,'Calès · salle des fêtes'),
        array('seniors-payrac','Vitali’Seniors',2,'17:30',45,'Payrac · salle polyvalente'),
        array('training','Vitali’Training',2,'18:30',60,'Payrac · salle polyvalente'),
        array('renfort-cales','Vitali’Renfort',4,'18:00',45,'Calès · salle des fêtes'),
        array('renfort-payrac','Vitali’Renfort',4,'19:05',45,'Payrac · salle polyvalente'),
    );
    $today = new DateTimeImmutable('today', kcfr_timezone());
    for ($day=0;$day<42;$day++) {
        $date=$today->modify('+'.$day.' days');
        if($date->format('Y-m-d')>kcfr_settings()['validated_until']){continue;}
        if(kcfr_collective_closed(kcfr_utc($date->getTimestamp()))){continue;}
        foreach($series as $s) {
            if((int)$date->format('N')!==$s[2]) {continue;}
            $at=new DateTimeImmutable($date->format('Y-m-d').' '.$s[3],kcfr_timezone());
            if($at->getTimestamp()<=time()) {continue;}
            $inserted=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}kcfr_sessions (series,title,venue,starts_at,duration,capacity) VALUES (%s,%s,%s,%s,%d,15)",$s[0],$s[1],$s[5],kcfr_utc($at->getTimestamp()),$s[4]));
            if($inserted===1){kcfr_queue_google_session((int)$wpdb->insert_id);}
        }
    }
}

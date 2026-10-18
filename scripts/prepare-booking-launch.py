"""Prepare current public booking pages for launch, without publishing or opening.

Uses the functional draft forms while retaining current public headers and layout.
Re-read sources before any later publication; readiness checks remain mandatory.
"""
from pathlib import Path
import hashlib,json,re,runpy
ROOT=Path(__file__).resolve().parent.parent
api=runpy.run_path(str(ROOT/'scripts/wordpress-draft-transfer.py'))['api']
OUT=ROOT/'artifacts/booking-launch-29'

def main():
    OUT.mkdir(parents=True,exist_ok=True)
    health=api('/wp-json/kcfr/v1/health')
    pages={}
    for ident in (39,40,68,69):
        p=api(f'/wp-json/wp/v2/pages/{ident}?context=edit')
        pages[ident]=p
        f=OUT/f'before-{ident}.json';f.write_text(json.dumps(p,ensure_ascii=False));f.chmod(0o600)
    replacements={
      'Démonstration : les réservations publiques ne sont pas encore ouvertes. Pour réserver réellement ou poser une question, appelez le 06 74 80 67 07.': 'Réservez votre place pour chaque séance. Pour une première venue ou une question sur le choix du cours, appelez Kévin au 06 74 80 67 07.',
      'Les dates de cet atelier servent aux essais. ': '',
      'À l’ouverture des réservations en ligne, un lien privé vous permettra de suivre ou d’annuler votre inscription.': 'Après votre réservation, conservez le lien privé affiché à l’écran et envoyé par e-mail pour suivre ou annuler votre inscription.',
      ' Pendant les tests, aucun e-mail client n’est envoyé.': ' Pensez à vérifier vos courriers indésirables.',
      'Les essais privés sont réservés à la préparation du site. Pour une inscription réelle ou pour toute question, contactez Kévin au <a href="tel:+33674806707">06 74 80 67 07</a>. Le mode test ne réserve pas une place pour un vrai cours.': 'Vous avez déjà une inscription pour ce cours ? Utilisez son lien privé de suivi. Si vous ne le retrouvez plus, contactez Kévin au <a href="tel:+33674806707">06 74 80 67 07</a>.',
      'La connexion Google Agenda est en cours de configuration ; le calcul automatique des trajets n’est pas actif.': 'Chaque demande reste à confirmer personnellement par Kévin. Le temps de trajet est vérifié avant confirmation.',
    }
    manifest=[]
    for public_id,draft_id,tags in [(39,68,['kcfr_booking','kcfr_coaching']),(40,69,['kcfr_manage'])]:
        raw=pages[public_id]['content']['raw']
        assert pages[public_id]['status']=='publish'
        for tag in tags:assert '['+tag+']' in pages[draft_id]['content']['raw']
        for old,new in replacements.items():raw=raw.replace(old,new)
        raw=re.sub(r'<p class="kcf20-demo"><strong>Démonstration : les réservations publiques ne sont pas encore ouvertes\.</strong>.*?</p>', '<p class="kcf20-demo">Réservez votre place pour chaque séance. Une question ? Appelez Kévin au <a href="tel:+33674806707">06 74 80 67 07</a>.</p>',raw,flags=re.S)
        if public_id==39:
            booking=r'<!-- wp:paragraph --><p>(?:<strong>)?Les réservations en ligne ne sont pas encore ouvertes\..*?</p><!-- /wp:paragraph -->'
            raw,n=re.subn(booking,'<!-- wp:shortcode -->[kcfr_booking]<!-- /wp:shortcode -->',raw,flags=re.S);assert n==1
            # Public release retained a contact paragraph in place of the coaching form.
            marker='<!-- wp:shortcode -->[kcfr_coaching]<!-- /wp:shortcode -->'
            draft=pages[draft_id]['content']['raw']
            pos=draft.index(marker)
            # Find the section introduction immediately preceding the draft form.
            paragraph=re.findall(r'<!-- wp:paragraph[^>]*-->.*?<!-- /wp:paragraph -->',draft[:pos],re.S)[-1]
            cleaned=paragraph
            for old,new in replacements.items():cleaned=cleaned.replace(old,new)
            assert cleaned in raw,'Coaching introduction changed; review before preparing'
            raw=raw.replace(cleaned,cleaned+'\n'+marker,1)
        else:
            closed=r'<!-- wp:paragraph --><p>Le suivi des inscriptions en ligne sera disponible.*?</p><!-- /wp:paragraph -->'
            raw,n=re.subn(closed,'<!-- wp:shortcode -->[kcfr_manage]<!-- /wp:shortcode -->',raw,flags=re.S);assert n==1
        assert not re.search(r'\?page_id=|preview=true',raw)
        assert all(raw.count('['+tag+']')==1 for tag in tags)
        remaining=[]
        for p in re.findall(r'<p\b[^>]*>.*?</p>',raw,re.S):
            if re.search(r'démonstration|mode test|atelier|essais privés|pas encore ouvertes',p,re.I):remaining.append(p)
        assert not remaining,remaining
        f=OUT/f'ready-{public_id}.html';f.write_text(raw);f.chmod(0o600)
        manifest.append({'public_page_id':public_id,'functional_forms':tags,'source_sha256':hashlib.sha256(pages[public_id]['content']['raw'].encode()).hexdigest(),'prepared_file':f.name})
    report={'published':False,'reservations_opened':False,'pages':manifest,'health':health,'remaining':['Install tested 1.0.1 or later without deleting existing data','Verify Google free/busy access and chosen calendar; sync real courses and confirm result','Resolve Google External Testing token lifetime for daily use','Configure and verify regular hosting cron','Publish functional content and change page IDs to 39/40 after confirming mail setting remains verified','Verify anonymous booking, cancellation and FIFO notifications before announcing launch']}
    (ROOT/'docs/booking-launch-29-readiness.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({'prepared_pages':[m['public_page_id'] for m in manifest],'public_pages_changed':False,'remaining':report['remaining']},ensure_ascii=False))
if __name__=='__main__':main()

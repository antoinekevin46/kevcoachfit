"""Render a branded motion montage from the existing fictional preview images.
No generated footage or real participant recording is implied. Original synthetic audio.
"""
from pathlib import Path
import subprocess,json,wave
import numpy as np
ROOT=Path(__file__).resolve().parent.parent
OUT=ROOT/'artifacts/visual-30';OUT.mkdir(parents=True,exist_ok=True)
ASSETS=ROOT/'wordpress/themes/kevcoachfit/assets'
FONT=ROOT/'wordpress/themes/kevcoachfit/fonts/open-sans-condensed-bold.ttf'
SANS=Path('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')
def run(args):subprocess.run(['ffmpeg','-hide_banner','-loglevel','error','-y',*args],check=True)
def text(key,value):
 p=OUT/(key+'.txt');p.write_text(value);return p
brand=text('brand','VITALI’VAN');site=text('site','kevcoachfit.com');note=text('note','VISUELS DE PRÉSENTATION · SCÈNES FICTIVES')
scenes=[('seniors','Vitali’Seniors','BOUGER EN CONFIANCE','Équilibre · Renforcement doux','b8e85c'),('renfort','Vitali’Renfort','RETROUVER SA FORCE','Posture · Tonification · Gainage','f5a45b'),('training','Vitali’Training','MONTER EN INTENSITÉ','Cardio · Force · Énergie','f5a45b'),('coach','Vitali’Coach','AVANCER À SON RYTHME','Coaching individuel à domicile','efa9c3')]
clips=[]
for i,(slug,title,hook,subtitle,color) in enumerate(scenes):
 titlef=text(f'title-{i}',title);hookf=text(f'hook-{i}',hook);subf=text(f'sub-{i}',subtitle)
 # Full exercise remains visible; blurred sides fill the wide frame instead of black bars.
 filters=(f"[0:v]split=2[bg][fg];[bg]scale=1536:864:force_original_aspect_ratio=increase,crop=1536:864,boxblur=24:3,eq=brightness=-0.28[back];"
 "[fg]scale=1296:864,zoompan=z='1+0.00012*on':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=144:s=1296x864:fps=24[front];"
 f"[back][front]overlay=(W-w)/2:0,setsar=1,drawbox=x=0:y=0:w=iw:h=96:color=0x101b16@0.78:t=fill,"
 f"drawtext=fontfile={FONT}:textfile={brand}:x=48:y=24:fontsize=40:fontcolor=0x{color},"
 f"drawtext=fontfile={SANS}:textfile={site}:x=w-tw-48:y=36:fontsize=22:fontcolor=white,"
 f"drawbox=x=0:y=530:w=iw:h=334:color=0x101b16@0.8:t=fill:enable='lt(t,3.9)',"
 f"drawbox=x=48:y=567:w=6:h=185:color=0x{color}:t=fill:enable='lt(t,3.9)',"
 f"drawtext=fontfile={SANS}:textfile={hookf}:x=78:y=560:fontsize=23:fontcolor=0x{color}:enable='lt(t,3.9)',"
 f"drawtext=fontfile={FONT}:textfile={titlef}:x=74:y=597:fontsize=94:fontcolor=white:enable='lt(t,3.9)',"
 f"drawtext=fontfile={SANS}:textfile={subf}:x=78:y=714:fontsize=28:fontcolor=white:enable='lt(t,3.9)',"
 f"drawtext=fontfile={SANS}:textfile={note}:x=48:y=826:fontsize=16:fontcolor=white@0.8,"
 f"drawbox=x=0:y=858:w='iw*t/6':h=6:color=0x{color}:t=fill[v]")
 # drawbox evaluates time differently; progress uses width animation via overlay instead.
 filters=filters.replace(f",drawbox=x=0:y=858:w='iw*t/6':h=6:color=0x{color}:t=fill",'')
 f=OUT/f'scene-{i}.mp4';run(['-loop','1','-i',str(ASSETS/('vitali-seniors-elastiques.webp' if slug=='seniors' else f'vitali-{slug}-interieur.webp')),'-filter_complex_threads','2','-filter_complex',filters,'-map','[v]','-t','6','-r','24','-c:v','libx264','-threads','2','-preset','fast','-crf','21','-pix_fmt','yuv420p',str(f)]);clips.append(f);print('Rendered',slug,flush=True)
endtitle=text('endtitle','VOTRE ÉNERGIE. VOTRE RYTHME.')
endline=text('endline','Cours à Calès et Payrac · Coaching autour de Loupiac')
endcta=text('endcta','Choisissez votre programme sur kevcoachfit.com')
end=OUT/'end.mp4'
run(['-f','lavfi','-i','color=c=0x101b16:s=1536x864:r=24:d=4','-vf',f"drawbox=x=72:y=160:w=96:h=7:color=0xb8e85c:t=fill,drawtext=fontfile={FONT}:textfile={brand}:x=72:y=202:fontsize=48:fontcolor=0xb8e85c,drawtext=fontfile={FONT}:textfile={endtitle}:x=72:y=303:fontsize=81:fontcolor=white,drawtext=fontfile={SANS}:textfile={endline}:x=72:y=450:fontsize=31:fontcolor=white,drawtext=fontfile={SANS}:textfile={endcta}:x=72:y=570:fontsize=29:fontcolor=0xb8e85c",'-c:v','libx264','-threads','2','-preset','fast','-crf','20','-pix_fmt','yuv420p',str(end)])
clips.append(end);args=[]
for c in clips:args+=['-i',str(c)]
trans='[0:v][1:v]xfade=transition=fade:duration=0.5:offset=5.5[v1];[v1][2:v]xfade=transition=fade:duration=0.5:offset=11[v2];[v2][3:v]xfade=transition=fade:duration=0.5:offset=16.5[v3];[v3][4:v]xfade=transition=fade:duration=0.5:offset=22[v]'
run([*args,'-filter_complex_threads','2','-filter_complex',trans,'-map','[v]','-c:v','libx264','-threads','2','-preset','fast','-crf','21','-pix_fmt','yuv420p',str(OUT/'silent.mp4')])
# An original instrumental bed: warm chords, a soft bass pulse and a restrained beat.
sr=44100;dur=26;t=np.arange(int(sr*dur))/sr;audio=np.zeros_like(t);beat=60/112
for start in np.arange(0,dur,beat):
 x=t-start;m=(x>=0)&(x<.45);u=x[m];audio[m]+=.19*np.sin(2*np.pi*(48*u+26*.035*(1-np.exp(-u/.035))))*np.exp(-u*14)
for start in np.arange(0,dur,beat/2):
 x=t-start;m=(x>=0)&(x<.07);u=x[m];audio[m]+=.025*(np.sin(2*np.pi*6731*u)+np.sin(2*np.pi*8317*u))*np.exp(-u*65)
for i,notes in enumerate([[130.81,164.81,196],[110,130.81,164.81],[174.61,220,261.63],[196,246.94,293.66]]):
 start=i*5.5;x=t-start;m=(x>=0)&(x<5.8);u=x[m];env=np.minimum(u/.6,1)*np.minimum((5.8-u)/.8,1)
 for f in notes:audio[m]+=.032*np.sin(2*np.pi*f*u)*env
fade=np.minimum(t/1.2,1)*np.minimum((dur-t)/2,1);audio=np.tanh(audio*1.7)*fade
with wave.open(str(OUT/'music.wav'),'wb') as w:w.setnchannels(1);w.setsampwidth(2);w.setframerate(sr);w.writeframes((audio*24000).astype('<i2').tobytes())
output=ASSETS/'vitalivan-film-2026.mp4'
run(['-i',str(OUT/'silent.mp4'),'-i',str(OUT/'music.wav'),'-c:v','copy','-c:a','aac','-b:a','128k','-shortest','-movflags','+faststart',str(output)])
run(['-ss','1','-i',str(output),'-frames:v','1',str(ASSETS/'vitalivan-film-poster.webp')])
print('Completed',output,output.stat().st_size,flush=True)

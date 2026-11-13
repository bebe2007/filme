<section class="content legal-page">
  <div class="legal-intro"><span class="section-kicker">Filme &amp; Seriale · Marian Bratu</span><h1><?=e($heading)?></h1><p><?=e($subheading)?></p><span class="legal-date">Versiune: 16 octombrie 2026</span></div>
  <div class="legal-layout"><nav class="legal-nav" aria-label="Informații și politici"><?php foreach($legalPages as $slug=>$copy):?><a href="/<?=e($slug)?>/"<?=$legalPage===$slug?' class="active" aria-current="page"':''?>><?=e($copy[0])?> <span aria-hidden="true">↗</span></a><?php endforeach;?><button type="button" data-privacy-open>Preferințe cookie <span aria-hidden="true">⚙</span></button></nav><article class="legal-copy"><?php render_legal_content($legalPage);?></article></div>
</section>

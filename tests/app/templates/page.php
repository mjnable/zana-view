<?php $this->layout('layouts/site', ['title' => 'About']) ?>
<?php $this->start('head') ?><meta name="description" content="<?= $description ?>"><?php $this->stop() ?>
<h1><?= $heading ?></h1>
<ul><?php foreach ($items as $item): ?><li><?= $item ?></li><?php endforeach ?></ul>
<p><?= $intro ?></p>
<p class="raw"><?= $this->raw('heading') ?></p>
<p class="object"><?= $this->e($author) ?></p>

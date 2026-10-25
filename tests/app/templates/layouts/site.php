<!doctype html>
<html lang="<?= $locale ?? 'en' ?>"><head><title><?= $title ?> | Site</title><?= $this->section('head') ?></head>
<body><?= $this->insert('partials/nav') ?><main><?= $this->section('content') ?></main></body></html>

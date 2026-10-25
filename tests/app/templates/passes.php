<?php $this->layout('layouts/plain', ['heading' => $name, 'shout' => $this->upper($this->raw('name'))]) ?>
<?= $this->insert('partials/name', ['label' => $name]) ?>

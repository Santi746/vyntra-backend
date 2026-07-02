<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cols = Schema::getColumnListing('club_member_roles');
echo 'club_member_roles columns: '.implode(', ', $cols).PHP_EOL;

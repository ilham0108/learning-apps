<?php

use Illuminate\Support\Facades\Schedule;

// Auto-submit attempt ujian yang melewati deadline (rules Bagian 9).
Schedule::command('exams:close-expired')->everyMinute()->withoutOverlapping();

// File ekspor laporan berlaku 7 hari; setelah itu file dihapus (risiko R16).
Schedule::command('exports:prune')->daily();

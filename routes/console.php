<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cargotrace:evaluate-delays')->hourly();

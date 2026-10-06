<?php

use Illuminate\Support\Facades\Route;

// Testbench's /_workbench route signs in the testbench.yaml workbench user and opens the panel.
Route::redirect('/', '/_workbench');

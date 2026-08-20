<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $jobs = [
        [
            'title' => 'Senior Laravel Developer',
            'company' => 'TechNova',
            'location' => 'Remote',
            'type' => 'Full-time',
        ],
        [
            'title' => 'Backend Engineer',
            'company' => 'CodeCrafters',
            'location' => 'New York, NY',
            'type' => 'Contract',
        ],
        [
            'title' => 'Full Stack Developer',
            'company' => 'WebSolutions Inc',
            'location' => 'San Francisco, CA',
            'type' => 'Full-time',
        ],
    ];

    return view('index', ['jobs' => $jobs]);
});

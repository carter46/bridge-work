<?php
/**
 * The 8 categories the original 30 listings are sorted into.
 * Slugs tech, operations, marketing, sales, design and education are kept from the old site,
 * so existing links like jobs.html?category=tech keep working.
 * Used by the seed migration and the verify report. Do not edit after deployment;
 * rename categories in Admin -> Categories instead.
 */

return [
    ['slug' => 'tech',       'name' => 'Tech & Development',           'icon' => 'terminal',      'sort' => 10, 'description' => 'Build and test the websites, apps and tools that modern businesses run on.'],
    ['slug' => 'support',    'name' => 'Customer & Technical Support', 'icon' => 'support_agent', 'sort' => 20, 'description' => 'Help customers and clients solve problems, from first contact to technical troubleshooting.'],
    ['slug' => 'operations', 'name' => 'Admin & Operations',           'icon' => 'inventory_2',   'sort' => 30, 'description' => 'Keep businesses running smoothly with remote administration, data and coordination roles.'],
    ['slug' => 'marketing',  'name' => 'Marketing & Content',          'icon' => 'campaign',      'sort' => 40, 'description' => 'Help brands grow through social media, writing, email and search.'],
    ['slug' => 'media',      'name' => 'Video & Motion',               'icon' => 'movie',         'sort' => 50, 'description' => 'Edit, animate and produce video content for brands and creators.'],
    ['slug' => 'sales',      'name' => 'Sales & Business Development', 'icon' => 'trending_up',   'sort' => 60, 'description' => 'Work with global brands to win new clients, build partnerships and grow revenue.'],
    ['slug' => 'design',     'name' => 'Design & Creative',            'icon' => 'palette',       'sort' => 70, 'description' => 'Shape the visual identity of global brands through graphic, product and brand design.'],
    ['slug' => 'education',  'name' => 'Education & Training',         'icon' => 'school',        'sort' => 80, 'description' => 'Empower learners worldwide through online tutoring, coaching and course production.'],
];

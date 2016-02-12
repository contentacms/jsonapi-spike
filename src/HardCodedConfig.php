<?php

/*
   This will ultimately belong in D8's standard configuration
   system. I'm hard-coding for to ship the minimum viable thing now.
*/

namespace Drupal\jsonapi;

class HardCodedConfig {
    public static $config = [
        'node' => [
            'article' => [
                'type' => 'articles',
                'include' => ['title'],
                'rename' => [
                    'nid' => 'id',
                    'field_byline' => 'byline',
                    'field_topic' => 'topic'
                ]
            ]
        ],
        'taxonomy_term' => [
            'topics' => [
                'type' => 'topics',
                'include' => ['name'],
                'rename' => [
                    'tid' => 'id'
                ]
            ]
        ]
    ];
}
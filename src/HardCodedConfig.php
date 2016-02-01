<?php

/* 
   This will ultimately belong in D8's standard configuration
   system. I'm hard-coding for to ship the minimum viable thing now.
*/

namespace Drupal\jsonapi;

function identity($key) {
    return function($object) {
        return $object->get($key);
    };
}

class HardCodedConfig {
    public static $config = [
        'node' => [
            'article' => [
                'include' => ['title'],
                'rename' => [
                    'field_byline' => 'byline',
                    'field_topic.name' => 'topic'
                ]
            ]
        ],
        'taxonomy_term' => [
            'topics' => [
                'include' => ['name', 'tid']
            ]
        ]
    ];
}
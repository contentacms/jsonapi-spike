<?php

/*
   This will ultimately belong in D8's standard configuration
   system. I'm hard-coding for to ship the minimum viable thing now.
*/

namespace Drupal\jsonapi;

class HardCodedConfig {
    private static $_config = [
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
                'include' => ['name'],
                'rename' => [
                    'tid' => 'id'
                ]
            ]
        ]
    ];

    public function __construct() {
        $this->config = $this->normalizeConfig(self::$_config);
    }

    public function configFor($object) {
        $entityTypeConfig = $this->config[$object->getEntityTypeId()];
        if (!$entityTypeConfig) {
            return array();
        }
        $bundleConfig = $entityTypeConfig[$object->bundle()];
        if (!bundleConfig) {
            return array();
        }
        return $bundleConfig;
    }

    private function normalizeConfig($config) {
        $normalized = [];
        foreach($config as $entityType => $entityTypeConfig) {
            $normalized[$entityType] = [];
            foreach($entityTypeConfig as $bundleId => $bundleConfig) {
                $normalized[$entityType][$bundleId] = $this->normalizeBundleConfig($bundleId, $bundleConfig);

            }
        }
        return $normalized;
    }

    private function normalizeBundleConfig($bundleId, $bundleConfig) {
        $output = [];
        if (isset($bundleConfig['type'])) {
            $output['type'] = $bundleConfig['type'];
        } else {
            $output['type'] = $bundleId;
        }
        $output['fields'] = [];
        if (isset($bundleConfig['include'])) {
            foreach ($bundleConfig['include'] as $key) {
                $output['fields'][$key] = [
                    "as" => $key
                ];
            }
        }
        if (isset($bundleConfig['rename'])) {
            foreach ($bundleConfig['rename'] as $key => $value) {
                $output['fields'][$key] = [
                    "as" => $value
                ];
            }
        }
        return $output;
    }
}
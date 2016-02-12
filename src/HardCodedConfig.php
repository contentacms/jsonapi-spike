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
                'fields' => [
                    'title' => 'title',
                    'nid' => 'id',
                    'field_byline' => 'byline',
                    'field_topic' => 'topic'
                ],
                'defaultInclude' => ['topic']
            ]
        ],
        'taxonomy_term' => [
            'topics' => [
                'fields' => [
                    'name' => 'name',
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

        if (isset($bundleConfig['defaultInclude'])) {
            $output['defaultInclude'] = array_map(function($path){
                return explode('.', $path);
            }, $bundleConfig['defaultInclude']);
        } else {
            $output['defaultInclude'] = [];
        }

        $output['fields'] = [];
        if (isset($bundleConfig['fields'])) {
            foreach ($bundleConfig['fields'] as $key => $value) {
                $output['fields'][$key] = [
                    "as" => $value
                ];
            }
        }
        return $output;
    }
}
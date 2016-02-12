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
                'fields' => [
                    'title' => 'title',
                    'field_byline' => 'byline',
                    'field_topic' => 'topic',
                    'content-type' => 'type'
                ],
                'defaultInclude' => ['topic']
            ]
        ],
        'taxonomy_term' => [
            'topics' => [
                'fields' => [
                    'name' => 'name',
                    'vocabulary' => 'type'
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

        if (isset($bundleConfig['defaultInclude'])) {
            $output['defaultInclude'] = array_map(function($path){
                return explode('.', $path);
            }, $bundleConfig['defaultInclude']);
        } else {
            $output['defaultInclude'] = [];
        }

        // These are the two mandatory fields we must expose to
        // JSONAPI. When the user doesn't configure them, we can
        // provide good defaults.
        $sawType = false;
        $sawId = false;

        if (isset($bundleConfig['fields'])) {
            foreach ($bundleConfig['fields'] as $key => $value) {
                if ($value == 'id') {
                    $sawId = true;
                }
                if ($value == 'type') {
                    $sawType = true;
                }
                $output['fields'][$key] = [
                    "as" => $value
                ];
            }
        }

        if (!$sawType) {
            $output['fields']['entity-type'] = [ 'as' => 'type' ];
        }

        if (!$sawId) {
            $output['fields']['id'] = [ 'as' => 'id' ];
        }


        return $output;
    }
}
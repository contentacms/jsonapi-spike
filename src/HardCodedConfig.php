<?php

/*
  This will ultimately belong in D8's standard configuration
  system. I'm hard-coding to ship the minimum viable thing now.
*/

namespace Drupal\jsonapi;

class HardCodedConfig {
  private static $_config = [
    // Versioned API mount point.
    '/jsonapi/v1' => [
      // An endpoint definition. Will expand to:
      //   /jsonapi/v1/nodes
      //   /jsonapi/v1/nodes/123
      // And assuming a "topic" entity reference is exposed below, also:
      //   /jsonapi/v1/nodes/123/relationships/topic
      //   /jsonapi/v1/nodes/123/topic
      '/nodes' => [
        // This endpoint will deal with Node entities
        'entityType' => 'node',

        // This is how we'll map the Node's content into
        // JSONAPI. This declares the maximal set of fields
        // exposed, clients can use sparse fieldsets to pare
        // it down smaller when they're uninterested in some
        // fields.
        'fields' => [
          'title' => 'title',
          'status' => 'published',
          'changed' => 'changed',
          'created' => 'created',
          // In this case we're choosing to make the Drupal
          // Content Type determine the JSONAPI type.
          'content-type' => 'type'
          // In general, you can discover what fields are
          // available for use in the left hand side here by
          // hitting the endpoint with ?debug=1 and seeing
          // the unused fields list.
        ],

        // fields and defaultIncludes can be extended on a
        // per-bundle basis.
        'extensions' => [
          // In this case, articles have some fields that
          // other nodes don't and we want to declare how
          // they should appear.
          'article' => [
            'fields' => [
              'field_body',
              'field_byline',
              'field_primary_image',
              'field_summary',
              'field_topic'
            ],
            // Embed related topic entities by default
            // (JSONAPI calls this is the "included"
            // section of a document). This is overridable
            // by query param. These entities will be
            // rendered based on their own endpoint config
            // in this same api version.
            'defaultInclude' => ['topic', 'primary-image']
          ],
          'event' => [
            'fields' => [
              'field_agenda',
              'field_contact_email',
              'field_contact_name',
              'field_credit_types',
              'field_end_date',
              'field_event_type',
              'field_featured',
              'field_host',
              'field_lu_credit',
              'field_materials',
              'field_overview',
              'field_primary_image',
              'field_register_url',
              'field_registration',
              'field_speakers',
              'field_sponsors',
              'field_start_date',
              'field_time_zone',
              'field_topic',
              'field_venue_address',
              'field_venue_city',
              'field_venue_country',
              'field_venue_name',
              'field_venue_postal_code',
              'field_venue_state'
            ],
            'defaultInclude' => ['topic', 'primary-image', 'event-type']
          ],
          'image' => [
            'fields' => [
              'title' => 'caption',
              'field_crops',
              'field_original_height',
              'field_original_width',
              'field_url' => 'url'
            ]
          ]
        ],
      ],
      '/topics' => [
        'entityType' => 'taxonomy_term',
        // This time we're restricting to a single bundle.
        'bundles' => ['topics'],
        'fields' => [
          'name' => 'name',
          'vocabulary' => 'type'
        ]
      ],
      '/event-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['event_types'],
        'fields' => [
          'name' => 'name',
          'bundle' => 'type'
        ]
      ]

    ]
  ];

  public function __construct() {
    $this->config = $this->prepareConfig(self::$_config);
  }

  public function endpoints() {
    $output = [];
    foreach($this->config as $scope => $config) {
      foreach($config['endpoints'] as $path => $endpointConfig) {
        $output[] = [ "scope" => $scope, "path" => $path ];
      }
    }
    return $output;
  }

  public function forEndpoint($scope, $endpoint) {
    $scopeConfig = $this->config[$scope];
    return [
      'scope' => $scopeConfig,
      'entryPoint' => &$scopeConfig['endpoints'][$endpoint],
    ];
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

  private function prepareConfig($config) {
    $prepared = [];
    foreach($config as $apiScope => $endpointConfigs) {
      $prepared[$apiScope] = $this->prepareApiScope($endpointConfigs);
      $prepared[$apiScope]["name"] = $apiScope;
    }
    return $prepared;
  }

  private function prepareApiScope($endpointConfigs) {
    $endpoints = [];
    $entities = [];
    $bundles = [];

    foreach($endpointConfigs as $path => $endpointConfig) {
      $nec = $this->prepareEndpointConfig($endpointConfig);
      $entityType = $nec['entityType'];
      $endpoints[$path] = $nec;
      if (isset($endpointConfig['bundles'])) {
        // the endpoint applies to only certain bundles,
        if (!isset($bundles[$entityType])) {
          $bundles[$entityType] = [];
        }
        foreach($endpointConfig['bundles'] as $bundleId) {
          $bundles[$entityType][$bundleId] = $path;
        }
      } else {
        // the endpoint applies to an entire entity type,
        $entities[$entityType] = $path;
      }
    }
    return [
      "endpoints" => $endpoints,
      "entities" => $entities,
      "bundles" => $bundles
    ];
  }

  private function prepareDefaultInclude($entry) {
    if (isset($entry['defaultInclude'])) {
      return array_map(function($path){
        return explode('.', $path);
      }, $entry['defaultInclude']);
    } else {
      return [];
    }
  }

  private function prepareFields($endpoint, $initial) {
    if ($initial) {
      $output = $initial;
    } else {
      $output = [];
    }

    // These are the two mandatory fields we must expose to
    // JSONAPI. When the user doesn't configure them, we can
    // provide good defaults.
    $sawType = false;
    $sawId = false;

    if (isset($endpoint['fields'])) {
      foreach ($endpoint['fields'] as $key => $value) {
        if (is_numeric($key)) {
          $key = $value;
          $value = strtolower(preg_replace('/^field-/', '', preg_replace('/_/', '-', $key)));
        }
        if ($value == 'id') { $sawId = true; }
        if ($value == 'type') { $sawType = true; }
        $output[$key] = [ "as" => $value ];
      }
    }

    if (!$sawType) {
      $output['entity-type'] = [ 'as' => 'type' ];
    }
    if (!$sawId) {
      $output['id'] = [ 'as' => 'id' ];
    }

    return $output;
  }

  private function prepareEndpointConfig($endpoint) {
    $output = [
      "entityType" => $endpoint['entityType'],
      "bundles" => isset($endpoint['bundles']) ? $endpoint['bundles'] : [],
      "defaultInclude" => $this->prepareDefaultInclude($endpoint),
      "fields" => $this->prepareFields($endpoint, null)
    ];

    if (isset($endpoint['extensions'])) {
      $output['extensions'] = [];
      foreach($endpoint['extensions'] as $bundle => $extension) {
        $output['extensions'][$bundle] = [
          "defaultInclude" => array_merge($output['defaultInclude'], $this->prepareDefaultInclude($extension)),
          "fields" => $this->prepareFields($extension, $output['fields'])
        ];
      }
    }

    return $output;
  }
}

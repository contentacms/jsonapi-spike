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
          'title',
          'status' => 'published',
          'changed',
          'created',
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
              'field_body' => [ "transform" => "json" ],
              'field_byline',
              'field_primary_image',
              'field_primary_image_caption',
              'field_deleted',
              'field_topic',
              'field_tile_sizes',
              'field_description',
              'field_audience',
              'field_tags',
              'field_hero_headline',
              'field_use_on_homepage',
              'field_hero_link_text',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_featured'
            ],
            // Embed related topic entities by default
            // (JSONAPI calls this is the "included"
            // section of a document). This is overridable
            // by query param. These entities will be
            // rendered based on their own endpoint config
            // in this same api version.
            'defaultInclude' => ['topic', 'primary-image', 'tags', 'audience', 'tile-sizes']
          ],
          'resource' => [
            'fields' => [
              'field_body' => [ "transform" => "json" ],
              'field_attachments_body' => [ "transform" => "json" ],
              'field_page_references' => [ "transform" => "json" ],
              'field_primary_image',
              'field_primary_image_caption',
              'field_topic',
              'field_deleted',
              'field_description',
              'field_tile_sizes',
              'field_audience',
              'field_tags',
              'field_members_only',
              'field_use_on_homepage',
              'field_featured',
              'field_resource_type',
              'field_subtitle'
            ],
            'defaultInclude' => [ 'topic', 'primary-image', 'tags', 'audience', 'resource-types', 'tile-sizes']
          ],
          'page' => [
            'fields' => [
              'field_tabs' => [ "transform" => "json" ],
              'field_body' => [ "transform" => "json" ],
              'field_primary_image',
              'field_primary_image_caption',
              'field_primary_image_is_hero',
              'field_deleted',
              'field_topic',
              'field_description',
              'field_use_on_homepage',
              'field_tile_sizes',
              'field_audience',
              'field_tags',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_featured',
              'field_product'
            ],
            'defaultInclude' => ['topic', 'primary-image', 'tags', 'audience', 'product', 'tile-sizes']
          ],
          'award' => [
            'fields' => [
              'field_tabs' => [ "transform" => "json" ],
              'field_first_box' => [ "transform" => "json" ],
              'field_second_box' => [ "transform" => "json" ],
              'field_body' => [ "transform" => "json" ],
              'field_primary_image',
              'field_primary_image_caption',
              'field_primary_image_is_hero',
              'field_deleted',
              'field_topic',
              'field_link_text',
              'field_tile_sizes',
              'field_hero_headline',
              'field_hero_link_text',
              'field_link_url',
              'field_description',
              'field_audience',
              'field_tags',
              'field_use_on_homepage',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_featured',
              'field_product'
            ],
            'defaultInclude' => ['topic', 'primary-image', 'tags', 'audience', 'product', 'tile-sizes']
          ],
          'showcase' => [
            'fields' => [
              'field_topic',
              'field_primary_image',
              'field_primary_image_caption',
              'field_tags',
              'field_audience',
              'field_architect_image',
              'field_architect_image_caption',
              'field_architect_name',
              'field_award_link',
              'field_award_text',
              'field_award_type',
              'field_award_year',
              'field_body' => [ "transform" => "json" ],
              'field_credit' => [ "transform" => "json" ],
              'field_deleted',
              'field_featured',
              'field_feature_type',
              'field_hero_headline',
              'field_hero_link_text',
              'field_use_on_homepage',
              'field_firm_link',
              'field_form_name',
              'field_tile_sizes',
              'field_first_box' => [ "transform" => "json" ],
              'field_jury' => [ "transform" => "json" ],
              'field_members_only',
              'field_project_address',
              'field_project_city',
              'field_project_state',
              'field_project_postal_code',
              'field_project_country',
              'field_project_information' => [ "transform" => "json" ],
              'field_project_name',
              'field_showcase_images',
              'field_showcase_image_captions' => [ "transform" => "json" ],
              'field_sub_award_type'
            ],
            'defaultInclude' => ['topic', 'primary-image', 'tags', 'audience', 'architect-image', 'award-type', 'feature-type', 'showcase-images', 'sub-award-type', 'tile-sizes']
          ],
          'event' => [
            'fields' => [
              'field_agenda' => [ 'transform' => 'json'],
              'field_contact_email',
              'field_contact_name',
              'field_credit_types',
              'field_end_date',
              'field_event_type',
              'field_deleted',
              'field_featured',
              'field_hero_headline',
              'field_hero_link_text',
              'field_use_on_homepage',
              'field_host',
              'field_link_text',
              'field_lu_credit',
              'field_materials' => [ 'transform' => 'json'],
              'field_overview' => [ 'transform' => 'json'],
              'field_primary_image',
              'field_tile_sizes',
              'field_primary_image_caption',
              'field_register_url',
              'field_registration' => [ 'transform' => 'json'],
              'field_speakers' => [ 'transform' => 'json'],
              'field_sponsors' => [ 'transform' => 'json'],
              'field_start_date',
              'field_time_zone',
              'field_topic',
              'field_venue_address',
              'field_venue_city',
              'field_venue_country',
              'field_venue_name',
              'field_venue_postal_code',
              'field_venue_state',
              'field_description',
              'field_audience',
              'field_tags',
              'field_publication_date',
              'field_prioritization',
              'field_members_only'
            ],
            'defaultInclude' => ['topic', 'primary-image', 'event-type', 'credit-types', 'audience', 'tags', 'tile-sizes']
          ],
          'call_to_action' => [
            'fields' => [
              'field_featured',
              'field_is_external_link',
              'field_deleted',
              'field_link_text',
              'field_link_url',
              'field_use_on_homepage',
              'field_description',
              'field_tags',
              'field_publication_date',
              'field_tile_sizes',
              'field_prioritization',
              'field_members_only'
            ],
            'defaultInclude' => ['tags', 'tile-sizes']
          ],
          'contract_document' => [
            'fields' => [
              'field_document_family',
              'field_document_number',
              'field_link_url',
              'field_deleted',
              'field_tile_sizes',
              'field_use_on_homepage',
              'field_description',
              'field_tags',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_featured'
            ],
            'defaultInclude' => ['tags', 'tile-sizes']
          ],
          'issue' => [
            'fields' => [
              'field_body' => [ 'transform' => 'json' ],
              'field_issue_type',
              'field_primary_image',
              'field_use_on_homepage',
              'field_primary_image_caption',
              'field_subtitle',
              'field_hero_headline',
              'field_hero_link_text',
              'field_byline',
              'field_description',
              'field_legislative_affairs',
              'field_topic',
              'field_deleted',
              'field_audience',
              'field_issue_type',
              'field_tile_sizes',
              'field_tags',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_take_action_url',
              'field_featured'
            ],
            'defaultInclude' => ['primary-image', 'issue-type', 'legislative-affairs', 'topic', 'audience', 'tags', 'tile-sizes']
          ],
          'course' => [
            'fields' => [
              'field_credit_types',
              'field_link_url',
              'field_lu_credit',
              'field_deleted',
              'field_description',
              'field_use_on_homepage',
              'field_tags',
              'field_tile_sizes',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_featured'
            ],
            'defaultInclude' => ['tags', 'credit-types', 'tile-sizes']
          ],
          'press_release' => [
            'fields' => [
              'field_body' => [ 'transform' => 'json' ],
              'field_contact_email',
              'field_contact_name',
              'field_contact_phone',
              'field_contact_twitter',
              'field_source',
              'field_tile_sizes',
              'field_subtitle',
              'field_description',
              'field_use_on_homepage',
              'field_deleted',
              'field_audience',
              'field_topic',
              'field_tags',
              'field_publication_date',
              'field_prioritization',
              'field_members_only',
              'field_featured'
            ],
            'defaultInclude' => [ 'tags', 'audience', 'topic']
          ],
          'image' => [
            'fields' => [
              'field_bytes',
              'field_cloudinary_id',
              'field_credit',
              'field_filename',
              'title' => 'description',
              'field_crops' => [ 'transform' => 'json'],
              'field_original_height',
              'field_original_width',
              'field_url' => 'url'
            ]
          ],
          'member' => [
            'fields' => [
              'title' => 'member-id',
              'field_professional_credentials'
            ]
          ],
          'bookmark' => [
            'fields' => [
              'field_node_id',
              'field_bookmark_type',
              'field_member_id'
            ]
          ],
          'customization' => [
            'fields' => [
              'title' => 'route',
              'field_first_image',
              'field_second_image',
              'field_third_image',
              'field_first_box' => [ 'transform' => 'json' ],
              'field_second_box' => [ 'transform' => 'json' ],
              'field_third_box' => [ 'transform' => 'json' ],
              'field_first_text_box',
              'field_second_text_box',
              'field_third_text_box',
              'field_first_tile' => [ 'transform' => 'json' ],
            ]
          ],
          'partner' => [
            'fields' => [
              'title' => 'name',
              'field_primary_image',
              'field_primary_image_caption',
              'field_body' => ['transform' => 'json'],
              'field_contact_info' => ['transform' => 'json'],
              'field_discount_info' => ['transform' => 'json'],
              'field_description',
              'field_use_on_homepage',
              'field_partner_with_us_link',
              'field_tile_sizes',
              'field_website',
              'field_partner_type',
              'field_partner_news' => ['transform' => 'json'],
            ],
            'defaultInclude' => [ 'primary-image', 'partner-type', 'tile-sizes']
          ],
          'best_practice' => [
            'fields' => [
              'field_primary_image',
              'field_subtitle',
              'field_description',
              'field_subchapter_number',
              'field_use_on_homepage',
              'field_subchapter_title',
              'field_deleted',
              'field_free_sample_link',
              'field_excerpt_note',
              'field_product_reference' => [ 'transform' => 'json' ],
              'field_tile_sizes',
              'field_summary' => [ 'transform' => 'json' ],
              'field_body' => [ 'transform' => 'json' ],
              'field_about_the_contributor' => [ 'transform' => 'json' ],
              'field_topic',
              'field_tags',
              'field_members_only',
              'field_featured',
              'field_chapter',
              'field_author_image'
            ],
            'defaultInclude' => ['topic', 'tags', 'audience', 'primary-image', 'chapter', 'author-image', 'tile-sizes']
          ]
        ]
      ],
      '/audiences' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['audience'],
        'fields' => [
          'name' => 'name',
          'vocabulary' => 'type'
        ]
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
      ],
      '/credit-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['credit_types'],
        'fields' => [
          'name' => 'name',
          'bundle' => 'type'
        ]
      ],
      '/issue-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['issue_types'],
        'fields' => [
          'name' => 'name',
          'bundle' => 'type'
        ]
      ],
      '/issue-topics' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['issue_topics'],
        'fields' => [
          'name' => 'name',
          'vocabulary' => 'type'
        ]
      ],
      '/legislative-affairs' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['legislative_affairs'],
        'fields' => [
          'name' => 'name',
          'vocabulary' => 'type'
        ]
      ],
      '/product-and-service-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['product_and_service_types'],
        'fields' => [
          'name' => 'name',
          'vocabulary' => 'type'
        ]
      ],
      '/resource-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['resource_types'],
        'fields' => [
          'name' => 'name',
          'vocabulary' => 'type'
        ]
      ],
      '/global-terms' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['global_terms'],
        'fields' => [
          'name',
          'bundle' => 'type',
          'parent' => 'parents'
        ]
      ],
      '/partner-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['partner_types'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
        ]
      ],
      '/best-practice-chapters' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['best_practice_chapters'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
        ]
      ],
      '/document-families' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['document_family'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
        ]
      ],
      '/award-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['award_types'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
        ]
      ],
      '/sub-award-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['sub_award_types'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
        ]
      ],
      '/tile-sizes' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['tile_sizes'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
        ]
      ],
      '/feature-types' => [
        'entityType' => 'taxonomy_term',
        'bundles' => ['feature_types'],
        'fields' => [
          'name',
          'vocabulary' => 'type'
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
          $value = [];
        }
        if (!is_array($value)) {
          $value = [ "as" => $value ];
        }
        if (!isset($value['as'])) {
          $value['as'] = strtolower(preg_replace('/^field-/', '', preg_replace('/_/', '-', $key)));
        }
        if ($value['as'] == 'id') { $sawId = true; }
        if ($value['as'] == 'type') { $sawType = true; }
        $output[$key] = $value;
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

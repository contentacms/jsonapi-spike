<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\jsonapi\HardCodedConfig;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class ContentEntityNormalizer extends NormalizerBase {

    /**
     * The interface or class that this Normalizer supports.
     *
     * @var array
     */
    protected $supportedInterfaceOrClass = ['Drupal\Core\Entity\ContentEntityInterface'];
    protected $format = array('jsonapi');

    protected function emptyConfig() {
        return [
            'include' => [],
            'rename' => []
        ];
    }

    protected function configFor($object) {
        $entityTypeConfig = HardCodedConfig::$config[$object->getEntityTypeId()];
        if (!$entityTypeConfig) {
            return array();
        }
        $bundleConfig = $entityTypeConfig[$object->bundle()];
        if (!bundleConfig) {
            return array();
        }
        return $bundleConfig;
    }

    protected function expandConfig($config) {
        $output = $this->emptyConfig();

        $include = $config['include'];

        foreach ($config['rename'] as $fromName => $toName) {
            $include[] = $fromName;
        }
        $output['rename'] = $config['rename'];
        $output['type'] = $config['type'];

        foreach ($include as $path) {
            $context = &$output;
            $parts = explode(".", $path);
            while (count($parts) > 1) {
                $key = array_shift($parts);
                if (!isset($context['include'][$key])) {
                    $context['include'][$key] = $this->emptyConfig();
                }
                $context = &$context['include'][$key];
            }
            $context['include'][$parts[0]] = true;
        }

        return $output;
    }

    protected function grabIncluded($object, $included, $context) {
        $attributes = [];
        $relationships = [];

        foreach ($object as $name => $field) {
            if (!$field->access('view', $context['account'])) {
                continue;
            }
            $innerContext = $context;
            $innerContext['has_parent'] = true;

            if (isset($included[$name])) {
                if (is_array($included[$name])) {
                    $innerContext['jsonapi_config'] = $included[$name];
                }
                $serialized = $this->serializer->normalize($field, $format, $innerContext);
                if (isset($serialized['meta']['d8_jsonapi_entity'])) {
                    // we found another entity, so this is a relationship
                    unset($serialized['meta']['d8_jsonapi_entity']);
                    if (count($serialized['meta']) == 0) {
                        unset($serialized['meta']);
                    }
                    $relationships[$name] = $serialized;
                } else {
                    // not another entity, so this is an attribute
                    $attributes[$name] = $serialized;
                }
            }
        }

        $record = ['attributes' => &$attributes];
        if (count($relationships) > 0) {
            $record['relationships'] = &$relationships;
        }

        return $record;
    }

    protected function doRenaming($record, $renames) {
        foreach ($renames as $fromPath => $toPath) {
            foreach (['attributes', 'relationships'] as $section) {
                if (isset($record[$section][$fromPath])) {
                    $context = &$record[$section];
                    $context[$toPath] = &$context[$fromPath];
                    unset($context[$fromPath]);
                }
            }
        }
        return $record;
    }

    protected function readPathAndPrune(&$context, $pathParts) {
        if (count($pathParts) > 1) {
            $key = array_shift($pathParts);
            $value = $this->readPathAndPrune($context[$key], $pathParts);
            if (count($context[$key]) == 0) {
                unset($context[$key]);
            }
        } else {
            $value = $context[$pathParts[0]];
            unset($context[$pathParts[0]]);
        }
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = NULL, array $context = array()) {
        $context += array(
            'account' => NULL,
            'jsonapi_path' => array(),
            'has_parent' => false
        );

        if (isset($context['jsonapi_config'])) {
            // Our parent is overriding our config
            $config = $context['jsonapi_config'];
        } else {
            // Look up this object's own configuration
            $config = $this->expandConfig($this->configFor($object));
        }

        $record = $this->grabIncluded($object, $config['include'], $context);
        $record = $this->doRenaming($record, $config['rename']);

        if (false) {
            $debug = $config;
            $debug['entityTypeId'] = $object->getEntityTypeId();
            $debug['bundleId'] = $object->bundle();
            $debug['keys'] = [];
            foreach ($object as $name => $field) {
                $debug['keys'][] = $name;
            }
            if (!isset($record['meta'])) {
                $record['meta'] = [];
            }
            $record['meta']['debug'] = $debug;
        }

        $record['id'] = $this->readPathAndPrune($record['attributes'], ['id']);
        $record['type'] = $config['type'];
        $response = [ 'data'  => &$record ];

        if ($context['has_parent']) {
            $response['meta'] = ['d8_jsonapi_entity' => true];
        }
        return $response;
    }

}

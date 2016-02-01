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
                $attributes[$name] = $this->serializer->normalize($field, $format, $innerContext);
            }
        }
        return $attributes;
    }

    protected function doRenaming($attributes, $renames) {
        foreach ($renames as $fromPath => $toPath) {
            $context = &$attributes;
            $parts = explode(".", $toPath);
            while (count($parts) > 1) {
                $key = array_shift($parts);
                if (!isset($context[$key])) {
                    $context[$key] = [];
                }
                $context = &$context[$key];
            }
            $context[$parts[0]] = $this->readPathAndPrune($attributes, explode(".", $fromPath));
        }
        return $attributes;
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

        $attributes = $this->grabIncluded($object, $config['include'], $context);
        $attributes = $this->doRenaming($attributes, $config['rename']);

        if (false) {
            $attributes['_debug'] = $config;
            $attributes['_debug']['entityTypeId'] = $object->getEntityTypeId();
            $attributes['_debug']['bundleId'] = $object->bundle();
            $attributes['_debug']['keys'] = [];
            foreach ($object as $name => $field) {
                $attributes['_debug']['keys'][] = $name;
            }

        }

        if (!$context['has_parent']) {
            return array('data' => $attributes);
        } else {
            return $attributes;
        }
    }

}

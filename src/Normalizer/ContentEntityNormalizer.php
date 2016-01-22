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

    protected function configFor($object) {
        $entityTypeConfig = HardCodedConfig::$config[$object->getEntityTypeId()];
        if (!$entityTypeConfig) {
            return array();
        }
        $bundleConfig = $entityTypeConfig[$object->bundle()];
        if (!bundleConfig) {
            return array();
        }

        // Merge empty defaults for easy access
        return $bundleConfig + [
            'include' => []
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = NULL, array $context = array()) {
        $context += array(
            'account' => NULL,
            'jsonapi_path' => array()
        );

        if (isset($context['jsonapi_config'])) {
            // We are a child object and will defer to our parent's config
            $config = $context['jsonapi_config'];
        } else {
            // We are the top level and may want to impose our own config
            $config = $this->configFor($object);
            $context['jsonapi_api'] = $config;
        }

        $attributes = [];
        foreach ($object as $name => $field) {
            if (!$field->access('view', $context['account'])) {
                continue;
            }
            $path = $context['jsonapi_path'];
            $path[] = $name;
            if (in_array(join('.', $path), $config['include'])) {
                $innerContext = $context;
                $innerContext['jsonapi_path'] = $path;
                $attributes[$name] = $this->serializer->normalize($field, $format, $innerContext);
            }
        }

        if ($context['jsonapi_path'] == '') {
            return array('data' => $attributes);
        } else {
            return $attributes;
        }
    }

}

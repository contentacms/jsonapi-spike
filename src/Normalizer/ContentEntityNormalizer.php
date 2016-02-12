<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\jsonapi\HardCodedConfig;
use Drupal\jsonapi\JsonApiEntityReference;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class ContentEntityNormalizer extends NormalizerBase {
    public function __construct() {
        $this->config = new HardCodedConfig();
    }

    /**
     * The interface or class that this Normalizer supports.
     *
     * @var array
     */
    protected $supportedInterfaceOrClass = ['Drupal\Core\Entity\ContentEntityInterface'];
    protected $format = array('jsonapi');

    protected function addMeta(&$record, $key, $value) {
        if (!$record['meta']) {
            $record['meta'] = [];
        }
        $record['meta'][$key] = $value;
    }

    // This exposes fields (in the JSONAPI sense) that are not fields
    // in the Drupal sense. They are eligible to be included as record
    // attributes or serve as the record's `type` or `id`.
    protected function coreFields($object) {
        $bundleLabel = strtolower(preg_replace('/\s/', '-', $object->getEntityType()->getBundleLabel()));
        return [
            'entity-type' => $object->getEntityTypeId(),
            'id' => $object->id(),
            'bundle-label' => $bundleLabel,
            $bundleLabel => $object->bundle()
        ];
    }

    protected function normalizeFields($object, $context, $doc) {
        $config = $this->config->configFor($object);
        $attributes = [];
        $relationships = [];
        $unused = [];

        foreach ($object as $name => $field) {
            if (!$field->access('view', $context['account'])) {
                continue;
            }

            if (isset($config['fields'][$name])) {
                $outputName = $config['fields'][$name]["as"];
                if (!$doc || $doc->shouldIncludeField($config['type'], $outputName)) {
                    $innerContext = $context;
                    $innerContext['jsonapi_path'][] = $outputName;
                    $child = $this->serializer->normalize($field, $format, $innerContext);
                    if ($child instanceof JsonApiEntityReference) {
                        $relationships[$outputName] = $child->normalize();
                    } else {
                        $attributes[$outputName] = $child;
                    }
                }
            } else {
                $unused[] = $name;
            }
        }

        foreach($this->coreFields($object) as $name => $value) {
            if (isset($config['fields'][$name])) {
                $outputName = $config['fields'][$name]["as"];
                $attributes[$outputName] = $value;
            } else {
                $unused[] = $name;
            }
        }

        $record = ['attributes' => &$attributes];
        if (count($relationships) > 0) {
            $record['relationships'] = &$relationships;
        }

        if ($doc && $doc->debugEnabled()) {
            $this->addMeta($record, 'unused-fields', $unused);
        }

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = NULL, array $context = array()) {
        $context += array(
            'account' => NULL,
            'jsonapi_path' => [] # Defaults to top level path, unless we've inherited one already.
        );

        if (isset($context['jsonapi_document'])) {
            $doc = $context['jsonapi_document'];
        } else {
            $doc = null;
        }

        $record = $this->normalizeFields($object, $context, $doc);
        $record['id'] = $record['attributes']['id'];
        unset($record['attributes']['id']);
        $record['type'] = $record['attributes']['type'];
        unset($record['attributes']['type']);

        if (count($context['jsonapi_path']) == 0) {
            return $record;
        } else {
            if (isset($context['jsonapi_document'])) {
                $doc = $context['jsonapi_document'];
                if ($doc->shouldInclude($context['jsonapi_path'])) {
                    $doc->addIncluded($record);
                }
            }
            return new JsonApiEntityReference($record);
        }

    }

}

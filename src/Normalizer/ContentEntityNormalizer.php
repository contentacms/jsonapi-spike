<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\jsonapi\JsonApiEntityReference;

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
            'bundle' => $object->bundle(),
            $bundleLabel => $object->bundle()
        ];
    }

    protected function normalizeFields($object, $context, $doc) {
        $attributes = [];
        $relationships = [];
        $unused = [];
        $coreFields = $this->coreFields($object);

        $fields = $doc->fieldsFor($coreFields['entity-type'], $coreFields['bundle']);
        $defaultInclude = null;
        if (count($context['jsonapi_path']) == 0) {
            $defaultInclude = $doc->defaultIncludeFor($coreFields['entity-type'], $coreFields['bundle']);
        }

        foreach ($object as $name => $field) {
            if (!$field->access('view', $context['account'])) {
                continue;
            }

            if (isset($fields[$name])) {
                $outputName = $fields[$name]["as"];
                if (true /* !$doc || $doc->shouldIncludeField($config['type'], $outputName)*/) {
                    $innerContext = $context;
                    if ($defaultInclude) {
                        $innerContext['jsonapi_default_include'] = $defaultInclude;
                    }
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

        foreach($coreFields as $name => $value) {
            if (isset($fields[$name])) {
                $outputName = $fields[$name]["as"];
                $attributes[$outputName] = $value;
            } else {
                $unused[] = $name;
            }
        }

        $record = ['attributes' => &$attributes];
        if (count($relationships) > 0) {
            $record['relationships'] = &$relationships;
        }

        if ($doc->debugEnabled()) {
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

        $doc = $context['jsonapi_document'];

        $record = $this->normalizeFields($object, $context, $doc);
        $record['id'] = $record['attributes']['id'];
        unset($record['attributes']['id']);
        $record['type'] = $record['attributes']['type'];
        unset($record['attributes']['type']);

        if (count($context['jsonapi_path']) == 0) {
            return $record;
        } else {
            if ($doc->shouldInclude($context['jsonapi_path'], $context['jsonapi_default_include'])) {
                $doc->addIncluded($record);
            }
            return new JsonApiEntityReference($record);
        }

    }

}

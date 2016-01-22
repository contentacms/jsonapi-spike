<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;

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

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = NULL, array $context = array()) {
        $context += array(
            'account' => NULL,
            'jsonapi_path' => array()
        );

        $forbidden = array('path', 'uid', 'revision_uid', 'field_topic.path');

        $attributes = [];
        foreach ($object as $name => $field) {
            $path = $context['jsonapi_path'];
            $path[] = $name;
            //print("checking " . join('.', $path) . "\n");
            if (!in_array(join('.', $path), $forbidden) && $field->access('view', $context['account'])) {
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

<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\FieldItemNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
/**
 * Converts the Drupal entity object structures to a normalized array.
 *
 * This is the default Normalizer for entities. All formats that have Encoders
 * registered with the Serializer in the DIC will be normalized with this
 * class unless another Normalizer is registered which supersedes it. If a
 * module wants to use format-specific or class-specific normalization, then
 * that module can register a new Normalizer and give it a higher priority than
 * this one.
 */
class FieldItemNormalizer extends NormalizerBase {

    /**
     * The interface or class that this Normalizer supports.
     *
     * @var string
     */
    protected $supportedInterfaceOrClass = 'Drupal\Core\Field\FieldItemInterface';

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = NULL, array $context = array()) {
        $name = $object->mainPropertyName();
        if ($name) {
            //print("matched name " . $name . " for " . get_class($object) . "\n");
            return $this->serializer->normalize($object->get($name), $format, $context);
        }
        $attributes = array();
        foreach ($object as $name => $field) {
            $attributes[$name] = $this->serializer->normalize($field, $format, $context);
        }
        return $attributes;
    }

}

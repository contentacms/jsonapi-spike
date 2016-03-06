<?php
namespace Drupal\jsonapi;

use Drupal\Component\Serialization\Json;

class Transforms {
  public function normalize($value, $transformName) {
    switch($transformName) {
    case 'json':
      if ($value) {
        return Json::decode($value);
      }
      break;
    default:
      return $value;
    }
  }
  public static function denormalize($value, $transformName) {
    switch($transformName) {
    case 'json':
      if ($value) {
        return Json::encode($value);
      }
      break;
    default:
      return $value;
    }
  }
}
<?php

namespace Drupal\jsonapi;

/*
   Represents a resource that has been sent to us by the client.
 */
class ResourceObject {
  public function __construct($data) {
    $this->type = $data['type'];
    $this->id = $data['id'];
    $this->meta = isset($data['meta']) ? $data['meta'] : [];
    $this->attributes = isset($data['attributes']) ? $data['attributes'] : [];
    $this->relationships = isset($data['relationships']) ? $data['relationships'] : [];
  }
}

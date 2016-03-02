<?php

namespace Drupal\jsonapi;

/*
   Represents a resource that has been sent to us by the client.
 */
class ResourceObject {
  public function __construct($data) {
    $this->type = $data['type'];
    $this->id = $data['id'];
    $this->meta = $data['meta'] || [];
    $this->attributes = $data['attributes'] || [];
    $this->relationships = $data['relationships'] || [];
  }
}

<?php

namespace Drupal\jsonapi;

/*
  This class represents a top-level JSONAPI document.

 */
class DocumentContext {
  public function __construct(RequestContext $req, $data=null) {
    $this->req = $req;

    // Our main payload, typically an Entity or list of Entities (when
    // we're representing content out of Drupal) or a ResourceObject
    // or list of ResourceObjects (when we're representing a request
    // that came from the client)
    $this->data = $data;

    // storage for any related resources
    $this->included = [];

    // Metadata (in the JSONAPI sense) that has been attached to this
    // document so it can be available to clients. We use this for
    // debug output, for example.
    $this->meta = null;


  }
  public function addIncluded($record) {
    $type = $record['type'];
    $id = $record['id'];
    if (!isset($this->included[$type])) {
      $this->included[$type] = [];
    }
    if (!isset($this->included[$type][$id])) {
      $this->included[$type][$id] = $record;
    }
  }

  public function allIncluded() {
    $output = [];
    foreach($this->included as $type => $values) {
      foreach($values as $id => $value) {
        $output[] = $value;
      }
    }
    return $output;
  }
  public function addMeta($key, $value) {
    if (!$this->meta) {
      $this->meta = [];
    }
    $this->meta[$key] = $value;
  }

  public function meta() {
    if ($this->req->debugEnabled()) {
      $this->addMeta('options', $this->req->options());
      $this->addMeta('config', $this->req->config());
    }
    return $this->meta;
  }
}
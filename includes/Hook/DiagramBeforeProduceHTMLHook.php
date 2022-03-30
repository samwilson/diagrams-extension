<?php

use File;

namespace MediaWiki\Extension\Diagrams\Hook;

interface DiagramsBeforeProduceHTMLHook {
  public function onDiagramBeforeProduceHTML(
    File $file,
    array &$imgAttrs
  );
}

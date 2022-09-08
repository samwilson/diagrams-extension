<?php

use File;

namespace MediaWiki\Extension\Diagrams\Hook;

interface DiagramsBeforeProduceHTMLHook {
  public function onDiagramsBeforeProduceHTML(
    File $file,
    array &$imgAttrs
  );
}

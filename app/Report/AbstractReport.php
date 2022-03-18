<?php

namespace Gazelle\Report;

abstract class AbstractReport extends \Gazelle\Base {

    /**
     * The context array is used to stash away pieces of information that will
     * be needed in the template, but are too complicated to derive within the
     * template. This is mainly needed for forum hackery and may be dispensed
     * with in the future.
     */
    protected array $context = [];

    abstract public function template(): string;

    public function subject(): \Gazelle\Base {
        return $this->subject; /** @phpstan-ignore-line */
    }

    public function context(): array {
        return $this->context;
    }

    public function showReason(): bool {
        return true;
    }
}

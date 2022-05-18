<?php

namespace DBLaci\Data;

/**
 * history trait for Etalon class
 *
 * Please implement abstract method in project trait, and use that trait. Not this one directly as you don't want to implement
 * history saving in every Etalon class!
 */
trait HistoryAbstract
{
    /**
     * Ignore key list.
     *
     * @return array
     */
    protected function getHistoryIgnoreKeys(): array
    {
        return [];
    }

    /**
     * Log changes to history.
     *
     * @param array $changeList
     */
    protected function logChangesToHistory(array $changeList)
    {
        if (count($changeList) === 0) {
            return;
        }

        $ignoreKeys = $this->getHistoryIgnoreKeys();

        $filteredChangeList = [];
        foreach ($changeList as $col => $values) {
            if (in_array($col, $ignoreKeys, true)) {
                continue;
            }

            $filteredChangeList[$col] = $values;
        }

        $this->saveToHistory($filteredChangeList);
    }

    /**
     * Save changes to history. See example history SQL!
     */
    abstract protected function saveToHistory(array $changeList): void;
}

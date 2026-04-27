<?php

/**
 * DISCLAIMER: The bigger part or all of the source code in this file is taken from the "Doctrine Collections"
 * [doctrine/collections](https://github.com/doctrine/collections). The file may have modifications from the
 * original source code in order to comply with the current requirements of this library. The author of these
 * changes does not pretend or claim any ownership or authorship of the original source code.
 */

declare(strict_types=1);

namespace Kraz\ReadModel\Collections;

/**
 * Interface for collections that allow efficient filtering with an expression API.
 *
 * Goal of this interface is a backend independent method to fetch elements
 * from a collections. {@link Expression} is crafted in a way that you can
 * implement queries from both in-memory and database-backed collections.
 *
 * For database backed collections this allows very efficient access by
 * utilizing the query APIs, for example SQL in the ORM. Applications using
 * this API can implement efficient database access without having to ask the
 * EntityManager or Repositories.
 *
 * @phpstan-template TKey as array-key
 * @phpstan-template-covariant T
 */
interface Selectable
{
    /**
     * Selects all elements from a selectable that match the expression and
     * returns a new collection containing these elements and preserved keys.
     *
     * @return ReadableCollection<mixed>&Selectable<mixed>
     * @phpstan-return ReadableCollection<TKey,T>&Selectable<TKey,T>
     */
    public function matching(Criteria $criteria): ReadableCollection;
}

<?php

/**
 * DISCLAIMER: The bigger part or all of the source code in this file is taken from the "Doctrine Collections"
 * [doctrine/collections](https://github.com/doctrine/collections). The file may have modifications from the
 * original source code in order to comply with the current requirements of this library. The author of these
 * changes does not pretend or claim any ownership or authorship of the original source code.
 */

declare(strict_types=1);

namespace Kraz\ReadModel\Collections;

enum Order: string
{
    case Ascending  = 'ASC';
    case Descending = 'DESC';
}

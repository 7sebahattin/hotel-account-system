<?php

declare(strict_types=1);

namespace App\Modules\Core\Trash;

use RuntimeException;

/** Çöp kutusu eyleminin kullanıcıya gösterilecek nedenle reddedilmesi (geri alma/kalıcı silme). */
class TrashActionFailed extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Schoenstatt;

use Spatie\SchemaOrg\ContactPoint;
use Spatie\SchemaOrg\DataDownload;
use Spatie\SchemaOrg\Dataset;
use Spatie\SchemaOrg\Organization;

/**
 * The two schema.org Dataset descriptions the shrine index publishes, English and
 * Spanish.
 *
 * A copy of Schoenstatt\Controller\SchoenstattController::getShrineDatasets(),
 * which is `protected` and so cannot be called from here even though it takes no
 * arguments and reads no state. Purely static content; the copy is pinned by
 * test/Integration/ShrineIndexParityTest.php, which reaches the original by
 * reflection and asserts the two arrays are identical.
 */
final class ShrineDatasets
{
    /** @return list<array<string, mixed>> */
    public static function build(): array
    {
        $creator = new Organization();
        $creator->url('https://schoenstatt.link/en/')
            ->sameAs('https://schoenstatt.link/es/')
            ->name('Schoenstatt Link')
            ->contactPoint((new ContactPoint())
                ->contactType('technical support')
                ->email('webmaster@schoenstatt.link')
                ->telephone('+1 414 215 0318'));

        $datasetEn = new Dataset();
        $datasetEn->name('Schoenstatt Shrine Database in English')
            ->description('Shrine database is a list of the Catholic Chapels belonging '
                . 'to the International Schoenstatt Movement')
            ->inLanguage('en')
            ->license('https://creativecommons.org/licenses/by-sa/3.0/')
            ->url('https://schoenstatt.link/en/shrines')
            ->keywords([
                'RELIGION > CATHOLIC CHURCH > MOVEMENTS',
                'RELIGION > CATHOLIC CHURCH > MARIAN SHRINES',
                'RELIGION > CATHOLIC CHURCH > MARY',
            ])
            ->creator($creator)
            ->distribution((new DataDownload())
                ->encodingFormat('JSON')
                ->contentUrl('https://schoenstatt.link/en/api/v1/associations/findByKind?kind=sch-shrine'));

        $datasetEs = new Dataset();
        $datasetEs->name('Base de datos de Santuarios de Schoenstatt en Español')
            ->description('La base de datos de los santuarios es una lista de capillas católicas perteneciente '
                . 'al Movimiento Apostólico de Schoenstatt')
            ->inLanguage('es')
            ->license('https://creativecommons.org/licenses/by-sa/3.0/')
            ->url('https://schoenstatt.link/es/shrines')
            ->keywords([
                'RELIGIÓN > IGLESIA CATÓLICA > MOVIMIENTOS',
                'RELIGIÓN > IGLESIA CATÓLICA > SANTUARIOS MARIANOS',
                'RELIGIÓN > IGLESIA CATÓLICA > MARÍA',
            ])
            ->creator($creator)
            ->distribution((new DataDownload())
                ->encodingFormat('JSON')
                ->contentUrl('https://schoenstatt.link/es/api/v1/associations/findByKind?kind=sch-shrine'));

        return [$datasetEn->toArray(), $datasetEs->toArray()];
    }
}

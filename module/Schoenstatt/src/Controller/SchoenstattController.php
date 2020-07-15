<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/Schoenstatt for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\SearchForm;
use Zend\View\Model\ViewModel;
use Spatie\SchemaOrg\Dataset;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\ContactPoint;
use Spatie\SchemaOrg\DataDownload;
use JUser\Model\UserTable;

class SchoenstattController extends AbstractActionController
{
    protected $schoenstattTable;
    protected $schConfig;

    protected $userTable;
    public function __construct(SchoenstattTable $schoenstattTable, array $config, UserTable $userTable)
    {
        $this->schoenstattTable = $schoenstattTable;
        $this->schConfig = $config['schoenstatt'];
        $this->userTable = $userTable;
    }

    public function indexAction()
    {
        if (! $this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('welcome');
        }

        $table = $this->schoenstattTable;

        $config = $this->schConfig;
        if (! isset($config['general_presidium_id']) || ! is_numeric($config['general_presidium_id'])) {
            throw new \Exception('Please set the "general_presidium_id" configuration.');
        }
        $generalPresidiumId = $config['general_presidium_id'];
        $generalPresidium = $table->getAssociation($generalPresidiumId);
        $nationalLeaders = $table->getNationalMovementsLeaders();

        $form = new SearchForm();

        return new ViewModel([
            'form'              => $form,
            'generalPresidiumId' => $generalPresidiumId,
            'generalPresidium'  => $generalPresidium,
            'nationalLeaders'   => $nationalLeaders,
        ]);
    }

    public function v1Action()
    {
        return [];
    }

    public function shrinesAction()
    {
        $table = $this->schoenstattTable;
        $shrines = $table->getShrines();

        //separate by countryRegion
        $regions = [];
        $regionStats = [];
        foreach ($shrines as $associationId => $object) {
            if (! isset($regions[$object['countryRegion']])) {
                $regions[$object['countryRegion']] = [];
            }
            if (! isset($regionStats[$object['countryRegion']])) {
                $regionStats[$object['countryRegion']] = [
                    'id' => $object['countryRegion'] . '-progress-bar',
                    'maxScore' => 0,
                    'score' => 0,
                ];
            }
            $regions[$object['countryRegion']][$associationId] = &$shrines[$associationId];
            $regionStats[$object['countryRegion']]['maxScore'] += 10;
            $regionStats[$object['countryRegion']]['score'] += $object['dataScore'];
        }
        $totalScore = 0;
        $totalMaxScore = 0;
        foreach ($regionStats as $region => $stats) {
            $totalScore += $stats['score'];
            $totalMaxScore += $stats['maxScore'];
            if ($stats['score'] > 0 && $stats['maxScore'] > 0) {
                $percent = floor($stats['score'] / $stats['maxScore'] * 100);
                $regionStats[$region]['percent'] = $percent;
            } else {
                $regionStats[$region]['percent'] = 0;
            }
        }
        $totalPercent = floor($totalScore / $totalMaxScore * 100);

        return new ViewModel([
//             'form'              => $form,
            'shrines'           => $shrines,
            'regions'           => $regions,
            'regionStats'       => $regionStats,
            'totalPercent'      => $totalPercent,
            'datasets'          => $this->getShrineDatasets(),
        ]);
    }

    public function waysideShrinesAction()
    {
        $table = $this->schoenstattTable;
        $shrines = $table->getWaysideShrines();

        //separate by countryRegion
        $regions = [];
        $regionStats = [];
        foreach ($shrines as $associationId => $object) {
            if (! isset($regions[$object['countryRegion']])) {
                $regions[$object['countryRegion']] = [];
            }
            if (! isset($regionStats[$object['countryRegion']])) {
                $regionStats[$object['countryRegion']] = [
                    'id' => $object['countryRegion'] . '-progress-bar',
                    'maxScore' => 0,
                    'score' => 0,
                ];
            }
            $regions[$object['countryRegion']][$associationId] = &$shrines[$associationId];
            $regionStats[$object['countryRegion']]['maxScore'] += 10;
            $regionStats[$object['countryRegion']]['score'] += $object['dataScore'];
        }
        $totalScore = 0;
        $totalMaxScore = 0;
        foreach ($regionStats as $region => $stats) {
            $totalScore += $stats['score'];
            $totalMaxScore += $stats['maxScore'];
            if ($stats['score'] > 0 && $stats['maxScore'] > 0) {
                $percent = floor($stats['score'] / $stats['maxScore'] * 100);
                $regionStats[$region]['percent'] = $percent;
            } else {
                $regionStats[$region]['percent'] = 0;
            }
        }
        $totalPercent = floor($totalScore / $totalMaxScore * 100);

        $view = new ViewModel([
//             'form'              => $form,
            'shrines'           => $shrines,
            'regions'           => $regions,
            'regionStats'       => $regionStats,
            'totalPercent'      => $totalPercent,
            'datasets'          => $this->getShrineDatasets(),
        ]);
        return $view;
    }

    public function submittingPhotosAction()
    {
        /**
         * @var \JUser\Model\UserTable $table
         */
//         $table = $this->userTable;
//         $user = $table->findById(5);
//         var_dump($user);

        return new ViewModel([]);
    }

    public function getShrineDatasets()
    {
        $datasets = [];
        $datasetEn = new Dataset();
        $creator = new Organization();
        $creator->url('https://schoenstatt.link/en/')
        ->sameAs('https://schoenstatt.link/es/')
        ->name('Schoenstatt Link')
        ->contactPoint((new ContactPoint())
            ->contactType('technical support')
            ->email('webmaster@schoenstatt.link')
            ->telephone('+1 414 215 0318'));
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
        $datasets = [$datasetEn->toArray(), $datasetEs->toArray()];
        return $datasets;
    }

    protected function getNationalMovementAssociationMap()
    {
        /** @var SchoenstattTable $table */
        $table = $this->schoenstattTable;
        $objects = $table->getObjects('association');
        $map = [];
        foreach ($objects as $associationId => $object) {
            if ('sch-national-movement' === $object['kind'] && isset($object['country'])) {
                $map[$object['country']] = $associationId;
            }
        }
        return $map;
    }

    protected function getCityCountryMap()
    {
        return [
            'Achumani/La Paz' => 'bo',
            'Alangasí, Quito' => 'ec',
            'Aloor' => 'in',
            'Antofagasta' => 'cl',
            'Araraquara' => 'br',
            'Arica' => 'cl',
            'Armenia' => 'co',
            'Asunción' => 'py',
            'Atibaia' => 'br',
            'Aulendorf' => 'de',
            'Austin' => 'us',
            'Aveiro' => 'pt',
            'Bad Salzdetfurth' => 'de',
            'Bangalore' => 'in',
            'Belgrano, Buenos Aires' => 'ar',
            'Belén de Escobar / Buenos Aires' => 'ar',
            'Berlin' => 'de',
            'Betzdorf' => 'de',
            'Bocholt' => 'de',
            'Bonn-Kreuzberg' => 'de',
            'Borken' => 'de',
            'Braga' => 'pt',
            'Brasilia' => 'br',
            'Brig, Vallis' => 'ch',
            'Buenos Aires' => 'ar',
            'Buin' => 'cl',
            'Bujumbura' => 'bi',
            'Bydgosczc' => 'pl',
            'Cabo Rojo' => 'pr',
            'Caieiras' => 'br',
            'Campsie Glen, Glasgow' => 'gb',
            'Canidelo/Vila Nova de Gaia/Porto' => 'pt',
            'Cathcart' => 'za',
            'Chilapa' => 'mx',
            'Chillán' => 'cl',
            'Ciudad del Este' => 'py',
            'Colina ' => 'cl',
            'Comodoro Rivadavia' => 'ar',
            'Concepción' => 'cl',
            'Confins, Belo Horizonte, Minas Gerais' => 'br',
            'Constantia/Cape Town' => 'za',
            'Cornélio Procópio' => 'br',
            'Crete / Lincoln, Nebraska' => 'us',
            'Curicó' => 'cl',
            'Curitiba' => 'br',
            'Córdoba, Cerro de Las Rosas' => 'br',
            'Dietershausen (Fulda)' => 'de',
            'Dortmund-Frohlinde' => 'de',
            'Emsdetten' => 'de',
            'Essen' => 'de',
            'Euskirchen' => 'de',
            'Florencio Varela' => 'ar',
            'Frederico Westphalen (Rio Grande do Sul)' => 'br',
            'Freiburg-Merzhausen' => 'de',
            'Fribourg' => 'ch',
            'Friedrichroda' => 'de',
            'Garanhuns/Pernambuco' => 'br',
            'Geilenkirchen' => 'de',
            'Gelsenkirchen-Horst' => 'de',
            'Gossau' => 'ch',
            'Greater Manchester' => 'gb',
            'Guarapuava' => 'br',
            'Guayaquil' => 'ec',
            'Hanover Park / Cape Town' => 'za',
            'Hatillo' => 'pr',
            'Heiligenstadt/Eichsfeld' => 'de',
            'Herxheim' => 'de',
            'Hillscheid' => 'de',
            'Horw Luzern' => 'ch',
            'Höpfingen-Waldstetten/Odenwald' => 'de',
            'Ibadan' => 'ng',
            'Ipacaray' => 'py',
            'Iquique' => 'cl',
            'Irinyalakuda' => 'in',
            'Isingiro' => 'tz',
            'Issum' => 'de',
            'Itaára / Santa Maria' => 'br',
            'Jacarezinho' => 'br',
            'Jaraguá, Sao Paulo' => 'br',
            'Johannesburg' => 'za',
            'Juana Díaz' => 'pr',
            'Józefow' => 'pl',
            'Karlsruhe' => 'de',
            'Kew, Melbourne' => 'au',
            'Koblenz-Metternich' => 'de',
            'Koszalin' => 'pl',
            'Köln' => 'de',
            'Kösching' => 'de',
            'La Florida, Santiago' => 'cl',
            'La Molina, Lima' => 'pe',
            'La Plata' => 'ar',
            'La Serena' => 'cl',
            'Las Condes, Santiago' => 'cl',
            'Lebach' => 'de',
            'Lisboa' => 'pt',
            'Londrina' => 'br',
            'Los Ángeles' => 'cl',
            'Luzern' => 'ch',
            'Madison' => 'us',
            'Madrid' => 'es',
            'Madurai, Tamil Nadu' => 'in',
            'Mala Subotica' => 'hr',
            'Mannheim' => 'de',
            'Mar del Plata' => 'ar',
            'Marienberg, Tabor-Heiligtum, Vallendar' => 'de',
            'Mendoza' => 'ar',
            'Meppen' => 'de',
            'Miami' => 'us',
            'Milwaukee' => 'us',
            'Monterrey' => 'mx',
            'Mulgoa, Sydney' => 'au',
            'Mutumba' => 'bi',
            'München' => 'de',
            'Münster-Gievenbeck' => 'de',
            'Nesselwang/Allgäu' => 'de',
            'New York' => 'us',
            'Nittenau (Regensburg)' => 'de',
            'Nueva Helvecia' => 'uy',
            'Oberkirch' => 'de',
            'Oberá' => 'br',
            'Obudvar' => 'hu',
            'Olinda/Recife' => 'br',
            'Opole' => 'pt',
            'Paderborn-Benhausen' => 'de',
            'Paraje Paso Mayor Municipio de Bahía Blanca' => 'ar',
            'Paraná' => 'ar',
            'Pereira' => 'co',
            'Perth' => 'au',
            'Porto Alegre' => 'br',
            'Poços de Caldas' => 'br',
            'Providencia, Santiago' => 'cl',
            'Puerto Montt' => 'cl',
            'Quarten' => 'ch',
            'Queretaro' => 'mx',
            'Quillota' => 'cl',
            'Quinta Normal, Santiago' => 'cl',
            'Quito' => 'ec',
            'Rancagua' => 'cl',
            'Rawson' => 'ar',
            'Reñaca' => 'cl',
            'Rio de Janeiro' => 'br',
            'Rockport, Lamar, Texas' => 'us',
            'Rodgau-Weiskirchen' => 'de',
            'Rokole' => 'cz',
            'Roma' => 'it',
            'Roma: Belmonte' => 'it',
            'Rosario' => 'ar',
            'Rottenburg-Ergenzingen' => 'de',
            'Salta' => 'ar',
            'Salvador' => 'br',
            'Samborondón' => 'ec',
            'San Antonio' => 'us',
            'San Fernando' => 'cl',
            'San Francisco de Macoris' => 'do',
            'San Isidro, Gran Buenos Aires' => 'ar',
            'San Luis de Potosí' => 'mx',
            'San Miguel de Tucumán' => 'ar',
            'Santa Ana' => 'cr',
            'Santa Cruz do Sul' => 'br',
            'Santa Maria' => 'br',
            'Santo Angelo' => 'br',
            'Santo Domingo' => 'do',
            'Sao Paulo' => 'br',
            'Schesslitz' => 'de',
            'Sendenhorst' => 'de',
            'Simmern' => 'de',
            'Sleepy Eye, Minnesota' => 'us',
            'St. Gallen' => 'ch',
            'Stuttgart' => 'de',
            'Swider / Otwock' => 'pl',
            'Talca' => 'cl',
            'Talisay' => 'ph',
            'Temuco' => 'cl',
            'Thrissur' => 'in',
            'Thun St. Martin' => 'fr',
            'Trier' => 'de',
            'Trujillo' => 'pe',
            'Valldoreix' => 'es',
            'Vallendar' => 'de',
            'Villa Ballester - Gran Buenos Aires' => 'ar',
            'Villa María/Cape Town' => 'za',
            'Villa Warcalde - Córdoba' => 'ar',
            'Visbek' => 'de',
            'Viña del Mar ' => 'cl',
            'Waltenhofen' => 'de',
            'Waukesha' => 'us',
            'Wien' => 'at',
            'Wiesbaden' => 'de',
            'Würzburg' => 'de',
            'Zabrze-Rokitnica' => 'pl',
        ];
    }

    protected function addUrlToObject($url, $label, &$object)
    {
        if (! isset($object['url1'])) {
            $object['url1'] = $url;
            $object['url1Label'] = $label;
        } elseif (! isset($object['url2'])) {
            $object['url2'] = $url;
            $object['url2Label'] = $label;
        } elseif (! isset($object['url3'])) {
            $object['url3'] = $url;
            $object['url3Label'] = $label;
        } else {
            throw new \Exception('We\'re out of urls...');
        }
    }
}

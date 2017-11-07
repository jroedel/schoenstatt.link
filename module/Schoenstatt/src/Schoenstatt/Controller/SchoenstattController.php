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
use libKML\Placemark;

class SchoenstattController extends AbstractActionController
{
    public function indexAction()
    {
        if (!$this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('welcome');
        }

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');

        $config = $sm->get('Schoenstatt\Config');
        if (!isset($config['general_presidium_id']) || !is_numeric($config['general_presidium_id'])) {
            throw new \Exception('Please set the "general_presidium_id" configuration.');
        }
        $generalPresidiumId = $config['general_presidium_id'];
        $generalPresidium = $table->getAssociation($generalPresidiumId);
        $nationalLeaders = $table->getNationalMovementsLeaders();

        $form = new SearchForm();

        return new ViewModel([
            'form'              => $form,
            'generalPresidiumId'=> $generalPresidiumId,
            'generalPresidium'  => $generalPresidium,
            'nationalLeaders'   => $nationalLeaders,
        ]);
    }

    public function shrinesAction()
    {
        if (!$this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('welcome');
        }

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');

        $shrines = $table->getShrines();

//         $form = new SearchForm();

        return new ViewModel([
//             'form'              => $form,
            'shrines'   => $shrines,
        ]);
    }

    public function importShrinesAction()
    {
        $simulate = '0' !== $this->params()->fromQuery('simulate', '1');

        $file = file_get_contents('data/import/santuarios.kml');
        //regex to separate Placemarks
        $re = '/<Placemark>(.*?)<\/Placemark>/mius';
        $reName = '/<name>(.*?)<\/name>/mius';
        $reDescription = '/<description>(.*?)<\/description>/mius';
        $reStyleUrl = '/<styleUrl>(.*?)<\/styleUrl>/mius';
        $reData = '/<Data name="([^"]*?)">\s*<value>(.+?)<\/value>\s*?<\/Data>/mius';
        $reCoordinates = '/<coordinates>\s*?([0-9\.-]+),([0-9\.-]+)(?:,([0-9\.-]+))?\s*?<\/coordinates>/mius';
        $reZip = '/[0-9][0-9\.-]{3,8}[0-9]/u';

        preg_match_all($re, $file, $features, PREG_SET_ORDER, 0);

        $utc = new \DateTimeZone('UTC');

        $cityCountryMap = $this->getCityCountryMap();
        $nationalMovementAssociationMap = $this->getNationalMovementAssociationMap();

        $dataToImport = [];
        $icons = [];
        foreach ($features as $feature) {
            //regex to separate fields
            $str = $feature[1];
            preg_match($reName, $feature[1], $nameMatch);
            preg_match($reDescription, $feature[1], $descriptionMatch);
            preg_match($reStyleUrl, $feature[1], $styleUrlMatch);
            preg_match_all($reData, $feature[1], $dataElements, PREG_SET_ORDER, 0);
            preg_match($reCoordinates, $feature[1], $coordinatesMatch);
            $name = $nameMatch[1];
            if (!isset($name)) {
                var_dump($nameMatch);
            }
            $description = isset($descriptionMatch[1]) ? $descriptionMatch[1] : null;
            $styleUrl = isset($styleUrlMatch[1]) ? $styleUrlMatch[1] : null;
            $latitude = isset($coordinatesMatch[2]) ? $coordinatesMatch[2] : null;
            $longitude = isset($coordinatesMatch[1]) ? $coordinatesMatch[1] : null;
            $data = [
                'name'      => $name,
                'kind'      => '#icon-503-DB4436' === $styleUrl ? 'sch-shrine' : 'sch-wayside-shrine',
                'adminTags' => 'auto-imported',
            ];
            if (empty($dataElements)) { //if we don't have data, the description is usually public or null
                $data['publicNotes'] = $description;
            } else {
                $data['adminNotes'] = $description;
            }
            foreach ($dataElements as $match) {
                if ('Lat' === $match[1]) {
                    if (!isset($latitude)) {
                        $latitude = $match[2];
                    }
                } elseif ('Long' === $match[1]) {
                    if (!isset($longitude)) {
                        $longitude = $match[2];
                    }
                } elseif ('Fecha Bendición' === $match[1]) {
                    $foundationDate = \DateTime::createFromFormat('!d/m/Y', $match[2], $utc);
                    if ($foundationDate) {
                        $data['foundationDate'] = $foundationDate;
                    }
                } elseif ('gx_media_links' === $match[1]) {
                    $this->addUrlToObject($match[2], 'Media', $data);
                } elseif ('URL Mapa' === $match[1]) {
                    $this->addUrlToObject($match[2], 'Map', $data);
                } elseif ('Web' === $match[1]) {
                    $this->addUrlToObject($match[2], 'Information', $data);
                } elseif ('Dirección / Cómo llegar' === $match[1]) {
                    preg_match($reZip, $match[2], $zipMatch);
                    if (isset($zipMatch[0])) {
                        $data['post1Zip'] = $zipMatch[0];
                    }
                    if (strlen($match[2]) < 200) {
                        $data['post1Street1'] = $match[2];
                    } else {
                        $data['contactNotes'] = $match[2];
                    }
                } elseif ('Ciudad' === $match[1]) {
                    $data['post1CityState'] = trim($match[2]);
                    if (!isset($cityCountryMap[$data['post1CityState']])) {
                        $cityCountryMap[$data['post1CityState']] = null;
                    } else {
                        $data['country'] = strtoupper($cityCountryMap[$data['post1CityState']]);
                        if (isset($nationalMovementAssociationMap[$data['country']])) {
                            $data['parentId'] = $nationalMovementAssociationMap[$data['country']];
                        }
                    }
                } else {
                    throw new \Exception('We missed a data field');
                }
            }
            //the GeoPoint will get set in the preprocessor
            $data['latitude'] = $latitude;
            $data['longitude'] = $longitude;

            //for analysis purposes
            if (!isset($icons[$styleUrl])) {
                $icons[$styleUrl] = [$name];
            } else {
                $icons[$styleUrl][] = $name;
            }

            $dataToImport[] = $data;
        }
        ksort($cityCountryMap);
        if (!$simulate) { //import the items
            /** @var SchoenstattTable $table */
            $table = $this->getServiceLocator()->get('Schoenstatt\Model\SchoenstattTable');
            $insertNow = false;
            foreach ($dataToImport as $key => $data) {
                if ($insertNow) {
                $dataToImport[$key]['result'] = $table->createEntity('association', $data, false);
                } elseif ('Valle Hermoso del Niño Jesús' === $data['name']) {
                    $insertNow = true;
                }
            }
        }
        return new ViewModel([
            'features' => $dataToImport,
            'simulate'  => $simulate,
        ]);
    }

    protected function getNationalMovementAssociationMap()
    {
        /** @var SchoenstattTable $table */
        $table = $this->getServiceLocator()->get('Schoenstatt\Model\SchoenstattTable');
        $objects = $table->getUnlinkedAssociations();
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
        if (!isset($object['url1'])) {
            $object['url1'] = $url;
            $object['url1Label'] = $label;
        } elseif (!isset($object['url2'])) {
            $object['url2'] = $url;
            $object['url2Label'] = $label;
        } elseif (!isset($object['url3'])) {
            $object['url3'] = $url;
            $object['url3Label'] = $label;
        } else {
            throw new \Exception('We\'re out of urls...');
        }
    }
}

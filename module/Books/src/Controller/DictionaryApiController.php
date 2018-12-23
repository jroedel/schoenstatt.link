<?php 
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Cocur\Slugify\Slugify;

class DictionaryApiController extends AbstractRestfulController
{
    public function slugifyTermsAction()
    {
        $request = $this->getRequest();
        $data = $this->processBodyContent($request);
        if (!is_array($data) || !isset($data['terms']) || !is_array($data['terms'])) {
            return new JsonModel(['message' => 'Not.']);
        }
        $slugify = new Slugify();
        $slugs = [];
        
        foreach ($data['terms'] as $term) {
            if (!is_string($term) && !isset($slugs[$term])) {
                $slugs[$term] = null;
            }
            $slugs[$term] = $slugify->slugify($term);
        }
        return new JsonModel([
            'slugs' => $slugs
        ], ['prettyPrint' => true]);
    }
}

<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Uri\Uri;
use Zend\Filter\ToNull;
use SionModel\Form\SionForm;

class AssociationForm extends SionForm implements InputFilterProviderInterface
{
	public function __construct()
	{
		parent::__construct('edit_association');
	}

	public function init()
	{
		$this->add([
		    'name' => 'name',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Association name',
		        'required' => true,
		    ],
		    'attributes' => [
		        'placeholder' => 'ex. Schoenstatt Fathers',
		        'maxlength' => '200',
		    ],
		]);
		$this->add([
		    'name' => 'isNameTranslateable',
		    'type' => 'Checkbox',
		    'options' => [
		        'label' => 'Should the name be translated?',
		        'checked_value' => '1',
		        'unchecked_value' => '0',
		        'use_hidden_element' => true,
		    ],
		]);
		$this->add([
			'name' => 'parent',
			'type' => 'Select',
			'options' => [
				'label' => 'Parent organization (for sorting purposes)',
		        'empty_option' => '',
		        'unselected_value' => '',
    			'disable_inarray_validator' => true,
			    'value_options' => [
			    ],
			],
			'attributes' => [
				'required' => false,
			],
		]);
		$this->add([
			'name' => 'kind',
			'type' => 'Select',
			'options' => [
				'label' => 'Association type',
			    'value_options' => [
			    ],
			],
			'attributes' => [
				'required' => true,
			],
		]);
		$this->add([
		    'name' => 'country',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Country (if not international)',
		        'empty_option' => '',
		        'unselected_value' => '',
			    'value_options' => [],// $this->customValueOptions['country'],
		    ],
		    'attributes' => [
		        'required' => false
		    ],
		]);

		$this->add([
		    'name' => 'foundationDate',
		    'type' => 'Date',
		    'options' => [
		        'label' => 'Foundation date',
		        'format' => 'Y-m-d',
		    ],
		    'attributes' => [
		        'min' => '1900-01-01',
		        'step' => 'any',
		        'required' => false,
		    ],
		]);
		$this->add([
		    'name' => 'isActive',
		    'type' => 'Checkbox',
		    'options' => [
		        'label' => 'Active',
		        'checked_value' => '1',
		        'unchecked_value' => '0',
		        'use_hidden_element' => true,
		    ],
		    'attributes' => [
		        'value'           => 1,
		        'data-toggle'     => 'collapse',
		        'data-target'     => '#canonicalSuppressionGroup',
		        'aria-expanded'   => 'false',
		        'aria-controls'   => 'canoicalSuppressionGroup'
		    ],
		]);
		$this->add([
		    'name' => 'suppressionDate',
		    'type' => 'Date',
		    'options' => [
		        'label' => 'Suppression date',
		        'format' => 'Y-m-d',
		    ],
		    'attributes' => [
		        'min' => '1900-01-01',
		        'step' => 'any',
		        'required' => false,
		    ],
		]);

		$this->add([
		    'name' => 'isLifeCommunity',
		    'type' => 'Checkbox',
		    'options' => [
		        'label' => 'Has lifetime membership?',
		        'checked_value' => '1',
		        'unchecked_value' => '0',
		        'use_hidden_element' => true,
		    ],
		]);


		$this->add([
		    'name' => 'email',
		    'type' => 'Email',
		    'options' => [
		        'label' => 'Main Email',
		    ],
		    'attributes' => [
		        'required' => false,
		        'maxlength' => '70',
		    ],
		]);
		$this->add([
			'name' => 'email2',
			'type' => 'Email',
			'options' => [
				'label' => 'Alternative Email',
			],
			'attributes' => [
            	'required' => false,
		        'maxlength' => '70',
			],
		]);
		$this->add([
		    'name' => 'phone1',
		    'type' => 'Phone',
		    'options' => [
		        'label' => 'Phone 1',
		        'help-block' => 'Please begin with a \'+\' followed by the country code.',
		    ],
		    'attributes' => [
		        'placeholder' => 'ex. +49 151 55555555',
		        'maxlength' => '30',
		    ],
		]);
		$this->add([
		    'name' => 'phone1Label',
			'type' => 'Select',
			'options' => [
				'label' => 'Phone 1 label (optional)',
				'required' => false,
		        'empty_option' => '',
		        'unselected_value' => '',
    			'disable_inarray_validator' => true,
			    'value_options' => [
			        'Alternate cell phone' => 'Alternate cell phone',
			        'Only WhatsApp' => 'Only WhatsApp',
			        'Movement house' => 'Movement house',
			        'Office' => 'Office',
			        'Parish' => 'Parish',
			        'Personal house phone' => 'Personal house phone',
			    ],
			],
		    'attributes' => [
		        'maxlength' => '50',
		    ],
		]);
		$this->add([
		    'name' => 'phone2',
		    'type' => 'Phone',
		    'options' => [
		        'label' => 'Phone 2',
		        'help-block' => 'Please begin with a \'+\' followed by the country code.',
		    ],
		    'attributes' => [
		        'placeholder' => 'ex. +49 151 55555555',
		        'maxlength' => '30',
		    ],
		]);
		$this->add([
		    'name' => 'phone2Label',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Phone 2 label (optional)',
				'required' => false,
		        'empty_option' => '',
    			'disable_inarray_validator' => true,
		        'unselected_value' => '',
			    'value_options' => [
			        'Alternate cell phone' => 'Alternate cell phone',
			        'Only WhatsApp' => 'Only WhatsApp',
			        'Movement house' => 'Movement house',
			        'Office' => 'Office',
			        'Parish' => 'Parish',
			        'Personal house phone' => 'Personal house phone',
			    ],
		    ],
		    'attributes' => [
		        'maxlength' => '50',
		    ],
		]);
		$this->add([
		    'name' => 'phone3',
		    'type' => 'Phone',
		    'options' => [
		        'label' => 'Phone 3',
		        'help-block' => 'Please begin with a \'+\' followed by the country code.',
		    ],
		    'attributes' => [
		        'placeholder' => 'ex. +49 151 55555555',
		        'maxlength' => '30',
		    ],
		]);
		$this->add([
		    'name' => 'phone3Label',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Phone 3 label (optional)',
				'required' => false,
		        'empty_option' => '',
    			'disable_inarray_validator' => true,
		        'unselected_value' => '',
			    'value_options' => [
			        'Alternate cell phone' => 'Alternate cell phone',
			        'Only WhatsApp' => 'Only WhatsApp',
			        'Movement house' => 'Movement house',
			        'Office' => 'Office',
			        'Parish' => 'Parish',
			        'Personal house phone' => 'Personal house phone',
			    ],
		    ],
		    'attributes' => [
		        'maxlength' => '50',
		    ],
		]);
		$this->add([
			'name' => 'twitterUser',
			'type' => 'Text',
			'options' => [
				'label' => 'Twitter user',
				'required' => false,
			],
		    'attributes' => [
		        'placeholder' => 'ex. fr_johnsmith',
		        'maxlength' => '15',
		    ],
		]);
		$this->add([
			'name' => 'instagramUser',
			'type' => 'Text',
			'options' => [
				'label' => 'Instagram user',
				'required' => false,
			],
		    'attributes' => [
		        'placeholder' => 'ex. fr_johnsmith',
		        'maxlength' => '30',
		    ],
		]);
		$this->add([
			'name' => 'facebookUrl',
			'type' => 'Url',
			'options' => [
				'label' => 'Facebook URL',
				'required' => false,
			    'uriHandler' => 'Zend\Uri\Http',
			    'allowRelative' => false,
			],
		    'attributes' => [
		        'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
		        'maxlength' => '255',
		    ],
		]);
		$this->add([
			'name' => 'url1',
			'type' => 'Url',
			'options' => [
				'label' => 'Other URL 1',
				'required' => false,
			    'uriHandler' => 'Zend\Uri\Http',
			    'allowRelative' => false,
			],
		    'attributes' => [
		        'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
		        'maxlength' => '255',
		    ],
		]);
		$this->add([
			'name' => 'url1Label',
			'type' => 'Select',
			'options' => [
				'label' => 'Other URL 1 Label',
				'required' => false,
		        'empty_option' => '',
    			'disable_inarray_validator' => true,
		        'unselected_value' => '',
			    'value_options' => [
			        'Blog' => 'Blog',
			        'G+' => 'G+',
			        'Personal website' => 'Personal website',
			    ],
			],
		    'attributes' => [
		        'maxlength' => '50',
		    ],
		]);
		$this->add([
			'name' => 'url2',
			'type' => 'Url',
			'options' => [
				'label' => 'Other URL 2',
				'required' => false,
			    'uriHandler' => 'Zend\Uri\Http',
			    'allowRelative' => false,
			],
		    'attributes' => [
		        'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
		        'maxlength' => '255',
		    ],
		]);
		$this->add([
			'name' => 'url2Label',
			'type' => 'Select',
			'options' => [
				'label' => 'Other URL 2 Label',
				'required' => false,
    			'disable_inarray_validator' => true,
		        'empty_option' => '',
		        'unselected_value' => '',
			    'value_options' => [
			        'Blog' => 'Blog',
			        'G+' => 'G+',
			        'Personal website' => 'Personal website',
			    ],
			],
		    'attributes' => [
		        'maxlength' => '50',
		    ],
		]);
		$this->add([
			'name' => 'url3',
			'type' => 'Url',
			'options' => [
				'label' => 'Other URL 3',
				'required' => false,
			    'uriHandler' => 'Zend\Uri\Http',
			    'allowRelative' => false,
			],
		    'attributes' => [
		        'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
		        'maxlength' => '255',
		    ],
		]);
		$this->add([
			'name' => 'url3Label',
			'type' => 'Select',
			'options' => [
				'label' => 'Other URL 3 Label',
				'required' => false,
    			'disable_inarray_validator' => true,
		        'empty_option' => '',
		        'unselected_value' => '',
			    'value_options' => [
			        'Blog' => 'Blog',
			        'G+' => 'G+',
			        'Personal website' => 'Personal website',
			    ],
			],
		    'attributes' => [
		        'maxlength' => '50',
		    ],
		]);

		$this->add([
		    'name' => 'post1Street1',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Street line 1 (post1)',
		    ],
		]);
		$this->add([
		    'name' => 'post1Street2',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Street line 2 (post1)',
		    ],
		]);
		$this->add([
		    'name' => 'post1CityState',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'City/State (post1)',
		    ],
		]);
		$this->add([
		    'name' => 'post1Zip',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Zip/PLZ (post1)',
		    ],
		]);
		$this->add([
		    'name' => 'post1Country',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Country (post1)',
		        'empty_option' => '',
		        'unselected_value' => '',
		    ],
		    'attributes' => [
		        'required' => false
		    ],
		]);

		$this->add([
		    'name' => 'post2Street1',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Street line 1 (post2)',
		    ],
		]);
		$this->add([
		    'name' => 'post2Street2',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Street line 2 (post2)',
		    ],
		]);
		$this->add([
		    'name' => 'post2CityState',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'City/State (post2)',
		    ],
		]);
		$this->add([
		    'name' => 'post2Zip',
		    'type' => 'Text',
		    'options' => [
		        'label' => 'Zip/PLZ (post2)',
		    ],
		]);
		$this->add([
		    'name' => 'post2Country',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Country (post2)',
		        'empty_option' => '',
		        'unselected_value' => '',
		    ],
		    'attributes' => [
		        'required' => false
		    ],
		]);

		$this->add([
			'name' => 'contactNotes',
			'type' => 'Textarea',
			'options' => [
				'label' => 'Contact detail notes',
				'required' => false,
			],
		]);

		$this->add([//http://www.codingdrama.com/bootstrap-markdown/
		    'name' => 'publicNotes',
		    'type' => 'Textarea',
		    'options' => [
		        'label' => 'Public notes',
		        'required' => false,
		    ],
		    'attributes' => [
		        'required' => false,
		        'data-provide' => 'markdown',
		        'data-parser' => 'CommonMark',
		        'rows' => 8,
		    ],
		    'filters' => [
		        ['name' => 'StripTags'],
		    ],
		]);

		$this->add([//http://www.codingdrama.com/bootstrap-markdown/
		    'name' => 'adminNotes',
		    'type' => 'Textarea',
		    'options' => [
		        'label' => 'Admin notes',
		        'required' => false,
		    ],
		    'attributes' => [
		        'required' => false,
		        'data-provide' => 'markdown',
		        'data-parser' => 'CommonMark',
		        'rows' => 8,
		    ],
		    'filters' => [
		        ['name' => 'StripTags'],
		    ],
		]);

		$this->add([
		    'name' => 'adminTags',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Admin tags',
		        'empty_option' => '',
		        'placeholder' => 'Select tags or type new ones...',
		        'unselected_value' => '',
    			'disable_inarray_validator' => true,
		        'value_options' => [],
		    ],
		    'attributes' => [
		        'required' => false,
		        'multiple' => true,
		    ],
		]);
/**
 * Common elements
 */
        $this->add([
		    'name' => 'associationId',
		    'type' => 'Hidden',
		]);
		$this->add([
			'name' => 'security',
			'type' => 'csrf',
		    'options' => [
                'csrf_options' => [
                     'timeout' => 600,
                ],
	        ],
		]);
		$this->add([
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => [
				'value' => 'Submit',
				'id' => 'submit',
				'class' => 'btn-primary'
			],
		]);
	}

	public function setInputFilterSpecification($spec)
	{
	    $this->filterSpec = $spec;
	}

	public function getInputFilterSpecification()
	{
	    if ($this->filterSpec) {
	        return $this->filterSpec;
	    }
		$this->filterSpec = [
		    'associationId'  => [
				'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
			],
		    'name' => [
				'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
	            'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 200,
                        ],
                    ],
	            ],
			],
		    'isNameTranslateable' => [
		        'required' => false,
		    ],
		    'parent' => [
		        'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_INTEGER,
                        ],
		            ],
                ],
		    ],
		    'kind' => [
		        'required' => true,
		    ],
		    'country' => [
		        'required' => false,
                'filters' => [
                    ['name' => 'StringToUpper'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
	            'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 2,
                        ],
                    ],
	            ],
		    ],
		    'foundationDate' => [
				'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
			],
		    'isActive' => [
		        'required' => false,
		    ],
		    'suppressionDate' => [
				'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
			],
		    'isLifeCommunity' => [
		        'required' => false,
		    ],
		    'email' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
		        ],
		        'validators' => [
		            ['name' => 'EmailAddress'],
		        ],
		    ],
		    'email2' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
		        ],
		        'validators' => [
		            ['name' => 'EmailAddress'],
		        ],
		    ],
			'phone1' => $this->phoneInputFilterSpec,
	        'phone1Label' => $this->phoneLabelInputFilterSpec,
	        'phone2' => $this->phoneInputFilterSpec,
	        'phone2Label' => $this->phoneLabelInputFilterSpec,
	        'phone3' => $this->phoneInputFilterSpec,
	        'phone3Label' => $this->phoneLabelInputFilterSpec,
		    'url1' => [
				'required' => false,
                'filters' => [
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'url1Label' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'url2' => [
				'required' => false,
                'filters' => [
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'url2Label' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'url3' => [
				'required' => false,
                'filters' => [
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'url3Label' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'facebookUrl' => [
				'required' => false,
                'filters' => [
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'twitterUser' => [
				'required' => false,
                'filters' => [
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
	            'validators' => [
                    ['name' => 'SionModel\Validator\Twitter'],
	            ],
			],
		    'instagramUser' => [
				'required' => false,
                'filters' => [
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
	            'validators' => [
                    ['name' => 'SionModel\Validator\Instagram'],
	            ],
		    ],
		    'post1Street1' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 70,
		                ],
		            ],
		        ],
		    ],
		    'post1Street2' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 70,
		                ],
		            ],
		        ],
		    ],
		    'post1CityState' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 40,
		                ],
		            ],
		        ],
		    ],
		    'post1Zip' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 15,
		                ],
		            ],
		        ],
		    ],
		    'post1Country' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		    ],
		    'post2Street1' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 70,
		                ],
		            ],
		        ],
		    ],
		    'post2Street2' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 70,
		                ],
		            ],
		        ],
		    ],
		    'post2CityState' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 40,
		                ],
		            ],
		        ],
		    ],
		    'post2Zip' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
		                'name' => 'StringLength',
		                'options' => [
		                    'encoding' => 'UTF-8',
		                    'max' => 15,
		                ],
		            ],
		        ],
		    ],
		    'post2Country' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		    ],
		    'contactNotes' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'publicNotes' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'adminNotes' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
		            ['name' => 'ToNull',
		                'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
		            ],
                ],
			],
		    'adminTags' => [
				'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
		    ],
		];
		return $this->filterSpec;
	}
}

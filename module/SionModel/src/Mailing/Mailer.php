<?php
namespace SionModel\Mailing;

use SionModel\I18n\TranslatesMessages;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Base class for application mailers: builds messages stamped with the
 * application's mail identity, renders their bodies from Twig templates through a
 * {@see TemplateRendererInterface}. It keeps no copy of what it sends: the `mailings`
 * table that held every body and recipient was dropped in db9.3.
 */
class Mailer
{
    /**
     * Relative to the application root, where the entry points chdir()
     * to. The old module-relative default ('/../../../public/css/…' from this
     * file) pointed inside the SionModel package, where the file has never
     * existed since the module was vendored into applications — every mail
     * went out unstyled, with only a PHP warning to show for it.
     */
    const CSS_PATH_DEFAULT = 'public/css/email-default.css';

    /**
     * @var TransportInterface $transport
     */
    protected $transport;

    /**
     * @var TemplateRendererInterface $renderer
     */
    protected $renderer;

    /**
     * @var TranslatesMessages $translator
     */
    protected $translator;

    /**
     * @var string $textDomain
     */
    protected $textDomain;

    /**
     * @var bool $isTranslatorEnabled
     */
    protected $isTranslatorEnabled;

    /**
     * @var array $config
     */
    protected $config;

    public function __construct(
        TransportInterface $transport,
        TemplateRendererInterface $renderer,
        $translator,
        array $config
    ) {
        $this->transport = $transport;
        $this->renderer  = $renderer;
        $this->translator = $translator;
        $this->config    = $config;
    }

    /**
     * A message pre-addressed with the application's mail identity, taken from
     * the `sion_model.mail` config block (from, from_name, bcc).
     *
     * @return Email
     */
    public function createEmail()
    {
        $mailConfig = isset($this->config['sion_model']['mail']) && is_array($this->config['sion_model']['mail'])
            ? $this->config['sion_model']['mail']
            : [];
        $email = new Email();
        if (isset($mailConfig['from']) && '' !== $mailConfig['from']) {
            $fromName = isset($mailConfig['from_name']) ? (string) $mailConfig['from_name'] : '';
            $email->from(new Address($mailConfig['from'], $fromName));
        }
        $bcc = isset($mailConfig['bcc']) ? (array) $mailConfig['bcc'] : [];
        foreach ($bcc as $address) {
            $email->addBcc($address);
        }
        return $email;
    }

    /**
     * Render a template to an HTML string, for use as a message body.
     *
     * @param string $template a name the renderer resolves, e.g.
     *        `@sion-model/mailing/action-email.html.twig`
     * @param array $params
     * @return string
     */
    public function renderTemplate($template, array $params)
    {
        return $this->renderer->render((string) $template, $params);
    }

    /**
     * Inlines CSS rules in an HTML document
     * @todo Add a little caching so we don't have to read the same
     *      CSS file several times in the same PHP instance
     * @param string $body
     * @param string $cssPath path to the stylesheet, relative to the
     *      application root (or absolute)
     * @return string
     * @throws \RuntimeException when the stylesheet cannot be read: a missing
     *      file used to degrade silently to unstyled mail
     */
    public static function inlineEmailStyles($body, $cssPath = Mailer::CSS_PATH_DEFAULT)
    {
        $css = @file_get_contents($cssPath);
        if (false === $css) {
            throw new \RuntimeException(sprintf(
                'Email stylesheet not readable: %s (cwd: %s)',
                $cssPath,
                getcwd()
            ));
        }

        return CssInliner::inline($body, $css);
    }

    /**
     * @return TransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param TransportInterface $transport
     * @return $this
     */
    public function setTransport(TransportInterface $transport)
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * Sets translator to use in helper
     *
     * @param  TranslatesMessages $translator  [optional] translator.
     *                                           Default is null, which sets no translator.
     * @param  string              $textDomain  [optional] text domain
     *                                           Default is null, which skips setTranslatorTextDomain
     * @return self
     */
    public function setTranslator(?TranslatesMessages $translator = null, $textDomain = null)
    {
        $this->translator =  $translator;
        if (isset($textDomain)) {
            $this->setTranslatorTextDomain($textDomain);
        }
        return $this;
    }

    /**
     * Returns translator used in object
     *
     * @return TranslatesMessages|null
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Checks if the object has a translator
     *
     * @return bool
     */
    public function hasTranslator()
    {
        return isset($this->translator) && $this->translator instanceof TranslatesMessages;
    }

    /**
     * Sets whether translator is enabled and should be used
     *
     * @param  bool $enabled [optional] whether translator should be used.
     *                       Default is true.
     * @return self
     */
    public function setTranslatorEnabled($enabled = true)
    {
        $this->isTranslatorEnabled = (bool) $enabled;
        return $this;
    }

    /**
     * Returns whether translator is enabled and should be used
     *
     * @return bool
     */
    public function isTranslatorEnabled()
    {
        return (bool) $this->isTranslatorEnabled;
    }

    /**
     * Set translation text domain
     *
     * @param  string $textDomain
     * @return self
     */
    public function setTranslatorTextDomain($textDomain = 'default')
    {
        $this->textDomain = $textDomain;
        return $this;
    }

    /**
     * Return the translation text domain
     *
     * @return string
     */
    public function getTranslatorTextDomain()
    {
        return $this->textDomain;
    }
}

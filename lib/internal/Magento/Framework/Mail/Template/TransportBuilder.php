<?php
/**
 * Mail Template Transport Builder
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 * @noinspection PhpUnusedParameterInspection
 */
declare(strict_types=1);

namespace Magento\Framework\Mail\Template;

use Magento\Framework\App\TemplateTypesInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MailAddress;
use Magento\Framework\Mail\MailAddressConverter;
use Magento\Framework\Mail\MailAddressList;
use Magento\Framework\Mail\MailAddressListFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MessageInterfaceFactory;
use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\TemplateInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;

/**
 * TransportBuilder
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TransportBuilder
{
    /**
     * Template Identifier
     *
     * @var string
     */
    protected $templateIdentifier;

    /**
     * Template Model
     *
     * @var string
     */
    protected $templateModel;

    /**
     * Template Variables
     *
     * @var array
     */
    protected $templateVars;

    /**
     * Template Options
     *
     * @var array
     */
    protected $templateOptions;

    /**
     * Mail Transport
     *
     * @var TransportInterface
     */
    protected $transport;

    /**
     * Template Factory
     *
     * @var FactoryInterface
     */
    protected $templateFactory;

    /**
     * Object Manager
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Message
     *
     * @var EmailMessageInterface
     */
    protected $message;

    /**
     * Sender resolver
     *
     * @var SenderResolverInterface
     */
    protected $_senderResolver;

    /**
     * @var TransportInterfaceFactory
     */
    protected $mailTransportFactory;

    /**
     * Param that used for storing all message data until it will be used
     *
     * @var array
     */
    private $messageData = [];

    /**
     * @var EmailMessageInterfaceFactory
     */
    private $emailMessageInterfaceFactory;

    /**
     * @var MimeMessageInterfaceFactory
     */
    private $mimeMessageInterfaceFactory;

    /**
     * @var MimePartInterfaceFactory
     */
    private $mimePartInterfaceFactory;
    /**
     * @var MailAddressConverter|null
     */
    private $mailAddressConverter;

    /**
     * TransportBuilder constructor
     *
     * @param FactoryInterface $templateFactory
     * @param MessageInterface $message
     * @param SenderResolverInterface $senderResolver
     * @param ObjectManagerInterface $objectManager
     * @param TransportInterfaceFactory $mailTransportFactory
     * @param MessageInterfaceFactory|null $messageFactory
     * @param EmailMessageInterfaceFactory|null $emailMessageInterfaceFactory
     * @param MimeMessageInterfaceFactory|null $mimeMessageInterfaceFactory
     * @param MimePartInterfaceFactory|null $mimePartInterfaceFactory
     * @param MailAddressConverter|null $mailAddressConverter
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        FactoryInterface $templateFactory,
        MessageInterface $message,
        SenderResolverInterface $senderResolver,
        ObjectManagerInterface $objectManager,
        TransportInterfaceFactory $mailTransportFactory,
        MessageInterfaceFactory $messageFactory = null,
        EmailMessageInterfaceFactory $emailMessageInterfaceFactory = null,
        MimeMessageInterfaceFactory $mimeMessageInterfaceFactory = null,
        MimePartInterfaceFactory $mimePartInterfaceFactory = null,
        MailAddressConverter $mailAddressConverter=null
    ) {
        $this->templateFactory = $templateFactory;
        $this->objectManager = $objectManager;
        $this->_senderResolver = $senderResolver;
        $this->mailTransportFactory = $mailTransportFactory;
        $this->emailMessageInterfaceFactory = $emailMessageInterfaceFactory ?: $this->objectManager
            ->get(EmailMessageInterfaceFactory::class);
        $this->mimeMessageInterfaceFactory = $mimeMessageInterfaceFactory ?: $this->objectManager
            ->get(MimeMessageInterfaceFactory::class);
        $this->mimePartInterfaceFactory = $mimePartInterfaceFactory ?: $this->objectManager
            ->get(MimePartInterfaceFactory::class);
        $this->mailAddressConverter = $mailAddressConverter ?: $this->objectManager
            ->get(MailAddressConverter::class);
    }

    /**
     * Add cc address
     *
     * @param array|string $address
     * @param string $name
     *
     * @return $this
     * @throws MailException
     */
    public function addCc($address, $name = '')
    {
        $this->getMailAddresses('cc', $address, $name);

        return $this;
    }

    /**
     * Add to address
     *
     * @param array|string $address
     * @param string $name
     *
     * @return $this
     * @throws MailException
     */
    public function addTo($address, $name = '')
    {
        $this->getMailAddresses('to', $address, $name);

        return $this;
    }

    /**
     * Add bcc address
     *
     * @param array|string $address
     *
     * @return $this
     * @throws MailException
     */
    public function addBcc($address)
    {
        $this->getMailAddresses('bcc', $address);

        return $this;
    }

    /**
     * Set Reply-To Header
     *
     * @param string $email
     * @param string|null $name
     *
     * @return $this
     * @throws MailException
     */
    public function setReplyTo($email, $name = null)
    {

        $this->getMailAddresses('replyTo', $email, $name);

        return $this;
    }

    /**
     * Set mail from address
     *
     * @param string|array $from
     *
     * @return $this
     * @throws MailException
     * @see setFromByScope()
     *
     * @deprecated This function sets the from address but does not provide
     * a way of setting the correct from addresses based on the scope.
     */
    public function setFrom($from)
    {
        return $this->setFromByScope($from);
    }

    /**
     * Set mail from address by scopeId
     *
     * @param string|array $from
     * @param string|int $scopeId
     *
     * @return $this
     * @throws MailException
     */
    public function setFromByScope($from, $scopeId = null)
    {
        $result = $this->_senderResolver->resolve($from, $scopeId);
        $this->getMailAddresses('from', $result['email'], $result['name']);

        return $this;
    }

    /**
     * Set template identifier
     *
     * @param string $templateIdentifier
     *
     * @return $this
     */
    public function setTemplateIdentifier($templateIdentifier)
    {
        $this->templateIdentifier = $templateIdentifier;

        return $this;
    }

    /**
     * Set template model
     *
     * @param string $templateModel
     *
     * @return $this
     */
    public function setTemplateModel($templateModel)
    {
        $this->templateModel = $templateModel;
        return $this;
    }

    /**
     * Set template vars
     *
     * @param array $templateVars
     *
     * @return $this
     */
    public function setTemplateVars($templateVars)
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    /**
     * Set template options
     *
     * @param array $templateOptions
     * @return $this
     */
    public function setTemplateOptions($templateOptions)
    {
        $this->templateOptions = $templateOptions;

        return $this;
    }

    /**
     * Get mail transport
     *
     * @return TransportInterface
     * @throws LocalizedException
     */
    public function getTransport()
    {
        try {
            $this->prepareMessage();
            $mailTransport = $this->mailTransportFactory->create(['message' => clone $this->message]);
        } finally {
            $this->reset();
        }

        return $mailTransport;
    }

    /**
     * Reset object state
     *
     * @return $this
     */
    protected function reset()
    {
        $this->messageData = [];
        $this->templateIdentifier = null;
        $this->templateVars = null;
        $this->templateOptions = null;
        return $this;
    }

    /**
     * Get template
     *
     * @return TemplateInterface
     */
    protected function getTemplate()
    {
        return $this->templateFactory->get($this->templateIdentifier, $this->templateModel)
            ->setVars($this->templateVars)
            ->setOptions($this->templateOptions);
    }

    /**
     * Prepare message.
     *
     * @return $this
     * @throws LocalizedException if template type is unknown
     */
    protected function prepareMessage()
    {
        $template = $this->getTemplate();
        $content = $template->processTemplate();
        switch ($template->getType()) {
            case TemplateTypesInterface::TYPE_TEXT:
                $part['type'] = MimeInterface::TYPE_TEXT;
                break;

            case TemplateTypesInterface::TYPE_HTML:
                $part['type'] = MimeInterface::TYPE_HTML;
                break;

            default:
                throw new LocalizedException(
                    new Phrase('Unknown template type')
                );
        }
        $this->messageData['body'] = $this->mimeMessageInterfaceFactory
            ->create(['parts' => [$this->mimePartInterfaceFactory->create(['content'=>$content])]]);

        $this->messageData['subject'] = html_entity_decode(
            (string)$template->getSubject(),
            ENT_QUOTES
        );
        $this->message = $this->emailMessageInterfaceFactory->create($this->messageData);

        return $this;
    }

    /**
     * Handles possible incoming types of email (string or array)
     *
     * @param string $addressType
     * @param string|array $emailOrList
     * @param string|null $name
     *
     * @return void
     * @throws MailException
     */
    private function getMailAddresses(string $addressType, $emailOrList, ?string $name = null): void
    {
        if (is_array($emailOrList)) {
            $this->messageData[$addressType] = array_merge(
                $this->messageData[$addressType],
                $this->mailAddressConverter->convertMany($emailOrList)
            );

            return;
        }
        $this->messageData[$addressType][] = $this->mailAddressConverter->convert($emailOrList, $name);
    }
}

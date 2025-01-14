<?php

namespace Postmark;

use Postmark\Models\DynamicResponseModel as DynamicResponseModel;
use Postmark\PostmarkClientBase as PostmarkClientBase;
use Postmark\Models\PostmarkBounce;
use Postmark\Models\PostmarkBounceActivation;
use Postmark\Models\PostmarkBounceDump;
use Postmark\Models\PostmarkBounceList;
use Postmark\Models\PostmarkDeliveryStats;
use Postmark\Models\PostmarkResponse;
use Postmark\Models\PostmarkTemplate;
use Postmark\Models\PostmarkTemplateList;
use Postmark\Models\PostmarkClick;
use Postmark\Models\PostmarkClickList;
use Postmark\Models\PostmarkOpen;
use Postmark\Models\PostmarkOpenList;
use Postmark\Models\PostmarkServer;
use Postmark\Models\PostmarkServerList;
use Postmark\Models\PostmarkOutboundMessage;
use Postmark\Models\PostmarkOutboundMessageList;
use Postmark\Models\PostmarkInboundRuleTrigger;
use Postmark\Models\PostmarkInboundRuleTriggerList;
use Postmark\Models\PostmarkInboundMessage;
use Postmark\Models\PostmarkInboundMessageList;
use Postmark\Models\MessageStream\PostmarkMessageStream;
use Postmark\Models\MessageStream\PostmarkMessageStreamList;
use Postmark\Models\MessageStream\PostmarkMessageStreamArchivalConfirmation;
use Postmark\Models\Webhooks\WebhookConfiguration;
use Postmark\Models\Webhooks\WebhookConfigurationListingResponse;
use Postmark\Models\Suppressions\PostmarkSuppressionList;
use Postmark\Models\Suppressions\PostmarkSuppressionResultList;

/**
 * PostmarkClient provides the main functionality used to send and analyze email on a "per-Server"
 * basis. If you'd like to manage "Account-wide" configuration, see the PostmarkAdminClient.
 */
class PostmarkClient extends PostmarkClientBase {

	/**
	 * Create a new PostmarkClient.
	 *
	 * @param string $serverToken The token associated with "Server" you'd like to use to send/receive email from.
	 * @param integer $timeout The timeout, in seconds to wait for an API call to complete before throwing an Exception.
	 */
	function __construct($serverToken, $timeout = 60) {
		parent::__construct($serverToken, 'X-Postmark-Server-Token', $timeout);
	}

	/**
	 * Send an email.
	 *
	 * @param string $from The sender of the email. (Your account must have an associated Sender Signature for the address used.)
	 * @param string $to  The recipient of the email.
	 * @param string $subject  The subject of the email.
	 * @param string|null $htmlBody  The HTML content of the message, optional if Text Body is specified.
	 * @param string|null $textBody  The text content of the message, optional if HTML Body is specified.
	 * @param string|null $tag  A tag associated with this message, useful for classifying sent messages.
	 * @param boolean|null $trackOpens  True if you want Postmark to track opens of HTML emails.
	 * @param string|null $replyTo  Reply to email address.
	 * @param string|null $cc  Carbon Copy recipients, comma-separated
	 * @param string|null $bcc  Blind Carbon Copy recipients, comma-separated.
	 * @param array|null $headers  Headers to be included with the sent email message.
	 * @param array|null $attachments  An array of PostmarkAttachment objects.
	 * @param string|null $trackLinks  Can be any of "None", "HtmlAndText", "HtmlOnly", "TextOnly" to enable link tracking.
	 * @param array|null $metadata  Add metadata to the message. The metadata is an associative array, and values will be evaluated as strings by Postmark.
	 * @param string|null $messageStream  The message stream used to send this message. If not provided, the default transactional stream "outbound" will be used.
         * @return DynamicResponseModel
	 *@throws Models\PostmarkException
         *
	 */
	function sendEmail(
        string $from,
        string $to,
        string $subject,
        string $htmlBody = NULL,
        string $textBody = NULL,
        string $tag = NULL,
        bool   $trackOpens = NULL,
        string $replyTo = NULL,
        string $cc = NULL,
        string $bcc = NULL,
        array  $headers = NULL,
        array  $attachments = NULL,
        string $trackLinks = NULL,
        array  $metadata = NULL,
        string $messageStream = NULL): DynamicResponseModel
    {

		$body = array();
		$body['From'] = $from;
		$body['To'] = $to;
		$body['Cc'] = $cc;
		$body['Bcc'] = $bcc;
		$body['Subject'] = $subject;
		$body['HtmlBody'] = $htmlBody;
		$body['TextBody'] = $textBody;
		$body['Tag'] = $tag;
		$body['ReplyTo'] = $replyTo;
		$body['Headers'] = $this->fixHeaders($headers);
		$body['TrackOpens'] = $trackOpens;
		$body['Attachments'] = $attachments;
		$body['Metadata'] = $metadata;
		$body['MessageStream'] = $messageStream;

		// Since this parameter can override a per-server setting
		// we have to check whether it was actually set.
		// And only include it in the API call if that is the case.
		if ($trackLinks !== NULL) {
			$body['TrackLinks'] = $trackLinks;
		}

		return new DynamicResponseModel((array)$this->processRestRequest('POST', '/email', $body));
	}

	/**
	 * Send an email using a template.
	 *
	 * @param string $from The sender of the email. (Your account must have an associated Sender Signature for the address used.)
	 * @param string $to The recipient of the email.
	 * @param integer|string $templateIdOrAlias  The ID or alias of the template to use to generate the content of this message.
	 * @param array $templateModel  The values to combine with the Templated content.
	 * @param boolean $inlineCss  If the template contains an HTMLBody, CSS is automatically inlined, you may opt-out of this by passing 'false' for this parameter.
	 * @param string|null $tag  A tag associated with this message, useful for classifying sent messages.
	 * @param boolean|null $trackOpens  True if you want Postmark to track opens of HTML emails.
	 * @param string|null $replyTo  Reply to email address.
	 * @param string|null $cc  Carbon Copy recipients, comma-separated
	 * @param string|null $bcc  Blind Carbon Copy recipients, comma-separated.
	 * @param array|null $headers  Headers to be included with the sent email message.
	 * @param array|null $attachments  An array of PostmarkAttachment objects.
	 * @param string|null $trackLinks  Can be any of "None", "HtmlAndText", "HtmlOnly", "TextOnly" to enable link tracking.
	 * @param array|null $metadata  Add metadata to the message. The metadata is an associative array , and values will be evaluated as strings by Postmark.
	 * @param array|null $messageStream  The message stream used to send this message. If not provided, the default transactional stream "outbound" will be used.
	 * @return PostmarkResponse
	 */
	function sendEmailWithTemplate(
        string     $from,
        string     $to,
        int|string $templateIdOrAlias,
        array  $templateModel,
        bool   $inlineCss = true,
        string $tag = NULL,
        bool   $trackOpens = NULL,
        string $replyTo = NULL,
        string $cc = NULL,
        string $bcc = NULL,
        array  $headers = NULL,
        array  $attachments = NULL,
        string $trackLinks = NULL,
        array  $metadata = NULL,
        array  $messageStream = NULL): PostmarkResponse
    {
		$body = array();
		$body['From'] = $from;
		$body['To'] = $to;
		$body['Cc'] = $cc;
		$body['Bcc'] = $bcc;
		$body['Tag'] = $tag;
		$body['ReplyTo'] = $replyTo;
		$body['Headers'] = $this->fixHeaders($headers);
		$body['TrackOpens'] = $trackOpens;
		$body['Attachments'] = $attachments;
		$body['TemplateModel'] = $templateModel;
		$body['InlineCss'] = $inlineCss;
		$body['Metadata'] = $metadata;
		$body['MessageStream'] = $messageStream;

		// Since this parameter can override a per-server setting
		// we have to check whether it was actually set.
		// And only include it in the API call if that is the case.
		if ($trackLinks !== NULL) {
			$body['TrackLinks'] = $trackLinks;
		}

		if ( is_int( $templateIdOrAlias ) ) {
			$body['TemplateId'] = $templateIdOrAlias;

			// Uses the Template Alias if specified instead of Template ID.
		} else {
			$body['TemplateAlias'] = $templateIdOrAlias;
		}

		return new PostmarkResponse((array)$this->processRestRequest('POST', '/email/withTemplate', $body));
	}

	/**
	 * The Postmark API wants an Array of Key-Value pairs, not a dictionary object,
	 * therefore, we need to wrap the elements in an array.
	 */
	private function fixHeaders($headers) {
		$retval = NULL;
		if ($headers != NULL) {
			$retval = array();
			$index = 0;
			foreach ($headers as $key => $value) {
				$retval[$index] = array('Name' => $key, 'Value' => $value);
				$index++;
			}
		}
		return $retval;
	}

	/**
	 * Send multiple emails as a batch
	 *
	 * Each email is an associative array of values, but note that the 'Attachments'
	 * key must be an array of 'PostmarkAttachment' objects if you intend to send
	 * attachments with an email.
	 *
	 * @param array $emailBatch  An array of emails to be sent in one batch.
	 *
	 * @return DynamicResponseModel
	 */
	function sendEmailBatch($emailBatch = array()) {

		$final = array();

		foreach ($emailBatch as $email) {
			foreach ($email as $emailIdx => $emailValue) {
				if (strtolower($emailIdx) == 'headers') {
					$email[$emailIdx] = $this->fixHeaders($emailValue);
				}
			}
			array_push($final, $email);
		}

		return new DynamicResponseModel((array)$this->processRestRequest('POST', '/email/batch', $final));
	}

	/**
	 * Send multiple emails with a template as a batch
	 *
	 * Each email is an associative array of values. See sendEmailWithTemplate()
	 * for details on required values.
	 *
	 * @param array $emailBatch An array of emails to be sent in one batch.
	 *
	 * @return DynamicResponseModel
	 * @throws Models\PostmarkException
	 */
	function sendEmailBatchWithTemplate($emailBatch = array()) {
		$final = array();

		foreach ($emailBatch as $email) {
			foreach ($email as $emailIdx => $emailValue) {
				if (strtolower($emailIdx) === 'headers') {
					$email[$emailIdx] = $this->fixHeaders($emailValue);
				}
			}
			$final[] = $email;
		}

		return new DynamicResponseModel((array)$this->processRestRequest('POST', '/email/batchWithTemplates', array('Messages' => $final)));
	}

	/**
	 * Get an overview of the delivery statistics for all email that has been sent through this Server.
	 *
	 * @return PostmarkDeliveryStats
	 */
	function getDeliveryStatistics(): PostmarkDeliveryStats
    {
		return new PostmarkDeliveryStats((array)$this->processRestRequest('GET', '/deliverystats'));
	}

    /**
     * Get a batch of bounces to be processed.
     *
     * @param integer $count Number of bounces to retrieve
     * @param integer $offset How many bounces to skip (when paging through bounces.)
     * @param string|null $type The bounce type. (see http://developer.postmarkapp.com/developer-api-bounce.html#bounce-types)
     * @param bool|null $inactive Specifies if the bounce caused Postmark to deactivate this email.
     * @param string|null $emailFilter Filter by email address
     * @param string|null $tag Filter by tag
     * @param int|null $messageID Filter by MessageID
     * @param string|null $fromdate Filter for bounces after is date.
     * @param string|null $todate Filter for bounces before this date.
     * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
     * @return PostmarkBounceList
     */
	function getBounces(
        int  $count = 100,
        int $offset = 0,
        ?string $type = NULL,
        ?bool $inactive = NULL,
        ?string $emailFilter = NULL,
        ?string $tag = NULL,
        ?int $messageID = NULL,
        ?string $fromdate = NULL,
        ?string $todate = NULL,
        ?string $messagestream = NULL): PostmarkBounceList
    {

		$query = array();
		$query['type'] = $type;
		$query['inactive'] = $inactive;
		$query['emailFilter'] = $emailFilter;
		$query['tag'] = $tag;
		$query['messageID'] = $messageID;
		$query['count'] = $count;
		$query['offset'] = $offset;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new PostmarkBounceList((array)$this->processRestRequest('GET', '/bounces', $query));
	}

	/**
	 * Locate information on a specific email bounce.
	 *
	 * @param integer $id The ID of the bounce to get.
	 *
	 * If the $id value is greater than PHP_INT_MAX, the ID can be passed as a string.
	 *
	 * @return PostmarkBounce
	 */
	function getBounce(int $id): PostmarkBounce
    {
		return new PostmarkBounce((array)$this->processRestRequest('GET', "/bounces/$id"));
	}

    /**
     * Get a "dump" for a specific bounce.
     *
     * @param integer $id The ID of the bounce for which we want a dump.
     *
     * If the $id value is greater than PHP_INT_MAX, the ID can be passed as a string.
     *
     * @return PostmarkBounceDump
     */
	function getBounceDump(int $id): PostmarkBounceDump
    {
		return new PostmarkBounceDump((array)$this->processRestRequest('GET', "/bounces/$id/dump"));
	}

	/**
	 * Cause the email address associated with a Bounce to be reactivated.
	 *
	 * @param integer $id The bounce which has a deactivated email address.
	 *
	 * If the $id value is greater than PHP_INT_MAX, the ID can be passed as a string.
	 *
	 * @return PostmarkBounceActivation
	 */
	function activateBounce(int $id): PostmarkBounceActivation
    {
		return new PostmarkBounceActivation((array)$this->processRestRequest('PUT', "/bounces/$id/activate"));
	}

	/**
	 * Get the settings for the server associated with this PostmarkClient instance
	 * (defined by the $server_token you passed when instantiating this client)
	 *
	 * @return PostmarkServer
	 */
	function getServer(): PostmarkServer
    {
		return new PostmarkServer((array)$this->processRestRequest('GET', '/server'));
	}

	/**
	 * Modify the associated Server. Any parameters passed with NULL will be
	 * ignored (their existing values will not be modified).
	 *
	 * @param string|null $name Set the name of the server.
	 * @param string|null $color Set the color for the server in the Postmark WebUI (must be: 'purple', 'blue', 'turqoise', 'green', 'red', 'yellow', or 'grey')
	 * @param bool|null $rawEmailEnabled Enable raw email to be sent with inbound.
	 * @param bool|null $smtpApiActivated Specifies whether or not SMTP is enabled on this server.
	 * @param string|null $inboundHookUrl URL to POST to everytime an inbound event occurs.
	 * @param string|null $bounceHookUrl URL to POST to everytime a bounce event occurs.
	 * @param string|null $openHookUrl URL to POST to everytime an open event occurs.
	 * @param bool|null $postFirstOpenOnly If set to true, only the first open by a particular recipient will initiate the open webhook. Any subsequent opens of the same email by the same recipient will not initiate the webhook.
	 * @param bool|null $trackOpens Indicates if all emails being sent through this server have open tracking enabled.
	 * @param string|null $inboundDomain Inbound domain for MX setup.
	 * @param integer|null $inboundSpamThreshold The maximum spam score for an inbound message before it's blocked (range from 0-30).
	 * @param string|null $trackLinks Indicates if all emails being sent through this server have link tracking enabled.
	 * @param string|null $clickHookUrl URL to POST to everytime an click event occurs.
	 * @param string|null $deliveryHookUrl URL to POST to everytime an click event occurs.
	 * @return PostmarkServer
	 */
	function editServer(
        string $name = NULL,
        string $color = NULL,
        bool   $rawEmailEnabled = NULL,
        bool   $smtpApiActivated = NULL,
        string $inboundHookUrl = NULL,
        string $bounceHookUrl = NULL,
        string $openHookUrl = NULL,
        bool   $postFirstOpenOnly = NULL,
        bool   $trackOpens = NULL,
        string $inboundDomain = NULL,
        int    $inboundSpamThreshold = NULL,
        string $trackLinks = NULL,
        string $clickHookUrl = NULL,
        string $deliveryHookUrl = NULL): PostmarkServer
    {

		$body = array();
		$body["Name"] = $name;
		$body["Color"] = $color;
		$body["RawEmailEnabled"] = $rawEmailEnabled;
		$body["SmtpApiActivated"] = $smtpApiActivated;
		$body["InboundHookUrl"] = $inboundHookUrl;
		$body["BounceHookUrl"] = $bounceHookUrl;
		$body["OpenHookUrl"] = $openHookUrl;
		$body["PostFirstOpenOnly"] = $postFirstOpenOnly;
		$body["TrackOpens"] = $trackOpens;
		$body["InboundDomain"] = $inboundDomain;
		$body["InboundSpamThreshold"] = $inboundSpamThreshold;
		$body['trackLinks'] = $trackLinks;
		$body["ClickHookUrl"] = $clickHookUrl;
		$body["DeliveryHookUrl"] = $deliveryHookUrl;

		return new PostmarkServer((array)$this->processRestRequest('PUT', '/server', $body));
	}

    /**
     * Search messages that have been sent using this Server.
     *
     * @param integer $count How many messages to retrieve at once (defaults to 100)
     * @param integer $offset How many messages to skip when 'paging' through the massages (defaults to 0)
     * @param string|null $recipient Filter by recipient.
     * @param string|null $fromEmail Filter by sender email address.
     * @param string|null $tag Filter by tag.
     * @param string|null $subject Filter by subject.
     * @param string|null $status The current status for the outbound messages to return defaults to 'sent'
     * @param string|null $fromdate Filter to messages on or after YYYY-MM-DD
     * @param string|null $todate Filter to messages on or before YYYY-MM-DD
     * @param array|null $metadata An associative array of key-values that must all match values included in the metadata of matching sent messages.
     * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
     * @return PostmarkOutboundMessageList
     */
	function getOutboundMessages(
        int $count = 100,
        int $offset = 0,
        ?string $recipient = NULL,
        ?string $fromEmail = NULL,
        ?string $tag = NULL,
        ?string $subject = NULL,
        ?string $status = NULL,
        ?string $fromdate = NULL,
        ?string $todate = NULL,
        ?array $metadata = NULL,
        ?string $messagestream = NULL): PostmarkOutboundMessageList
    {
		$query = array();
		$query["recipient"] = $recipient;
		$query["fromemail"] = $fromEmail;
		$query["tag"] = $tag;
		$query["subject"] = $subject;
		$query["count"] = $count;
		$query["offset"] = $offset;
		$query["status"] = $status;
		$query["fromdate"] = $fromdate;
		$query["todate"] = $todate;
		$query['messagestream'] = $messagestream;

		if(!empty($metadata)) {
			foreach($metadata as $key => $value) {
				$query["metadata_$key"] = $value;
			}
		}

		return new PostmarkOutboundMessageList((array)$this->processRestRequest('GET', '/messages/outbound', $query));
	}

	/**
	 * Get information related to a specific sent message.
	 *
	 * @param string $id The ID of the Message for which we want details.
	 * @return DynamicResponseModel
	 */
	function getOutboundMessageDetails(string $id): DynamicResponseModel
    {
		return new DynamicResponseModel((array)$this->processRestRequest('GET', "/messages/outbound/$id/details"));
	}

	/**
	 * Get the raw content for a message that was sent.
	 * This response
	 * @param string $id The ID of the message for which we want a dump.
	 * @return DynamicResponseModel
	 */
	function getOutboundMessageDump(string $id): DynamicResponseModel
    {
		return new DynamicResponseModel((array)$this->processRestRequest('GET', "/messages/outbound/$id/dump"));
	}

	/**
	 * Get messages sent to the inbound email address associated with this Server.
	 *
	 * @param integer $count The number of inbounce messages to retrieve in the request (defaults to 100)
	 * @param integer $offset The number of messages to 'skip' when 'paging' through messages (defaults to 0)
	 * @param string|null $recipient Filter by the message recipient
	 * @param string|null $fromEmail Filter by the message sender
	 * @param string|null $tag Filter by the message tag
	 * @param string|null $subject Filter by the message subject
	 * @param string|null $mailboxHash Filter by the mailboxHash
	 * @param string|null $status Filter by status ('blocked' or 'processed')
	 * @param string|null $fromdate Filter to messages on or after YYYY-MM-DD
	 * @param string|null $todate Filter to messages on or before YYYY-MM-DD
	 * @return PostmarkInboundMessageList
	 */
	function getInboundMessages(
        int    $count = 100,
        int    $offset = 0,
        string $recipient = NULL,
        string $fromEmail = NULL,
        string $tag = NULL,
        string $subject = NULL,
        string $mailboxHash = NULL,
        string $status = NULL,
        string $fromdate = NULL,
        string $todate = NULL): PostmarkInboundMessageList
    {

		$query = array();
		$query['recipient'] = $recipient;
		$query['fromemail'] = $fromEmail;
		$query['tag'] = $tag;
		$query['subject'] = $subject;
		$query['mailboxhash'] = $mailboxHash;
		$query['count'] = $count;
		$query['status'] = $status;
		$query['offset'] = $offset;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;

		return new PostmarkInboundMessageList((array)$this->processRestRequest('GET', '/messages/inbound', $query));
	}

	/**
	 * Get details for a specific inbound message.
	 *
	 * @param string $id The ID of the message for which we went to get details.
	 * @return PostmarkInboundMessage
	 */
	function getInboundMessageDetails(string $id): PostmarkInboundMessage
    {
		return new PostmarkInboundMessage((array)$this->processRestRequest('GET', "/messages/inbound/$id/details"));
	}

	/**
	 * Allow an inbound message to be processed, even though the filtering rules would normally
	 * prevent it from being processed.
	 *
	 * @param string $id The ID for a message that we wish to unblock.
	 * @return DynamicResponseModel
	 */
	function bypassInboundMessageRules(string $id): DynamicResponseModel
    {
		return new DynamicResponseModel((array)$this->processRestRequest('PUT', "/messages/inbound/$id/bypass"));
	}

	/**
	 * Request that Postmark retry POSTing the specified message to the Server's Inbound Hook.
	 *
	 * @param string $id The ID for a message that we wish retry the inbound hook for.
	 * @return DynamicResponseModel
	 */
	function retryInboundMessageHook($id) {
		return new DynamicResponseModel((array)$this->processRestRequest('PUT', "/messages/inbound/$id/retry"));
	}

	/**
	 * Get statistics for tracked messages, optionally filtering by various open event properties.
	 *
	 * @param integer $count The number of open statistics to retrieve in this request.
	 * @param integer $offset The number of statistics to 'skip' when paging through statistics.
	 * @param string|null $recipient Filter by recipient.
	 * @param string|null $tag Filter by tag.
	 * @param string|null $clientName Filter by Email Client name.
	 * @param string|null $clientCompany Filter by Email Client Company's name.
	 * @param string|null $clientFamily Filter by Email Client's Family name.
	 * @param string|null $osName Filter by Email Client's Operating System Name.
	 * @param string|null $osFamily Filter by Email Client's Operating System's Family.
	 * @param string|null $osCompany Filter by Email Client's Operating System's Company.
	 * @param string|null $platform Filter by Email Client's Platform Name.
	 * @param string|null $country Filter by Country.
	 * @param string|null $region Filter by Region.
	 * @param string|null $city Filter by City.
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return PostmarkOpenList
     */
	function getOpenStatistics(
        int    $count = 100,
        int    $offset = 0,
        string $recipient = NULL,
        string $tag = NULL,
        string $clientName = NULL,
        string $clientCompany = NULL,
        string $clientFamily = NULL,
        string $osName = NULL,
        string $osFamily = NULL,
        string $osCompany = NULL,
        string $platform = NULL,
        string $country = NULL,
        string $region = NULL,
        string $city = NULL,
        string $messagestream = NULL): PostmarkOpenList
    {
    	$query = array();
		$query['count'] = $count;
		$query['offset'] = $offset;
		$query['recipient'] = $recipient;
		$query['tag'] = $tag;
		$query['client_name'] = $clientName;
		$query['client_company'] = $clientCompany;
		$query['client_family'] = $clientFamily;
		$query['os_name'] = $osName;
		$query['os_family'] = $osFamily;
		$query['os_company'] = $osCompany;
		$query['platform'] = $platform;
		$query['country'] = $country;
		$query['region'] = $region;
		$query['city'] = $city;
		$query['messagestream'] = $messagestream;

		return new PostmarkOpenList((array)$this->processRestRequest('GET', '/messages/outbound/opens', $query));
	}

	/**
	 * Get statistics for tracked messages, optionally filtering by various click event properties.
	 *
	 * @param integer $count The number of click statistics to retrieve in this request.
	 * @param integer $offset The number of statistics to 'skip' when paging through statistics.
	 * @param string|null $recipient Filter by recipient.
	 * @param string|null $tag Filter by tag.
	 * @param string|null $clientName Filter by Email Client name.
	 * @param string|null $clientCompany Filter by Email Client Company's name.
	 * @param string|null $clientFamily Filter by Email Client's Family name.
	 * @param string|null $osName Filter by Email Client's Operating System Name.
	 * @param string|null $osFamily Filter by Email Client's Operating System's Family.
	 * @param string|null $osCompany Filter by Email Client's Operating System's Company.
	 * @param string|null $platform Filter by Email Client's Platform Name.
	 * @param string|null $country Filter by Country.
	 * @param string|null $region Filter by Region.
	 * @param string|null $city Filter by City.
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return PostmarkClickList
	 */
	function getClickStatistics(
        int    $count = 100,
        int    $offset = 0,
        string $recipient = NULL,
        string $tag = NULL,
        string $clientName = NULL,
        string $clientCompany = NULL,
        string $clientFamily = NULL,
        string $osName = NULL,
        string $osFamily = NULL,
        string $osCompany = NULL,
        string $platform = NULL,
        string $country = NULL,
        string $region = NULL,
        string $city = NULL,
        string $messagestream = NULL): PostmarkClickList
    {

		$query = array();
		$query['count'] = $count;
		$query['offset'] = $offset;
		$query['recipient'] = $recipient;
		$query['tag'] = $tag;
		$query['client_name'] = $clientName;
		$query['client_company'] = $clientCompany;
		$query['client_family'] = $clientFamily;
		$query['os_name'] = $osName;
		$query['os_family'] = $osFamily;
		$query['os_company'] = $osCompany;
		$query['platform'] = $platform;
		$query['country'] = $country;
		$query['region'] = $region;
		$query['city'] = $city;
		$query['messagestream'] = $messagestream;

		return new PostmarkClickList((array)$this->processRestRequest('GET', '/messages/outbound/clicks', $query));
	}

	/**
	 * Get information about individual opens for a sent message.
	 *
	 * @param string $id The ID for the message that we want statistics for.
	 * @param integer $count How many statistics should we retrieve?
	 * @param integer $offset How many should we 'skip' when 'paging' through statistics.
	 * @return PostmarkOpenList
	 */
	function getOpenStatisticsForMessage(string $id, int $count = 100, int $offset = 0): PostmarkOpenList
    {
		$query = array();

		$query['count'] = $count;
		$query['offset'] = $offset;

		return new PostmarkOpenList((array)$this->processRestRequest('GET', "/messages/outbound/opens/$id", $query));
	}

	/**
	 * Get information about individual clicks for a sent message.
	 *
	 * @param  string $id The ID for the message that we want statistics for.
	 * @param  integer $count How many statistics should we retrieve?
	 * @param  integer $offset How many should we 'skip' when 'paging' through statistics.
	 * @return DynamicResponseModel
	 */
	function getClickStatisticsForMessage($id, $count = 100, $offset = 0) {
		$query = array();

		$query['count'] = $count;
		$query['offset'] = $offset;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', "/messages/outbound/clicks/$id", $query));
	}

	/**
	 * Get an overview of the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param string|null $tag Filter by tag.
	 * @param string|null $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $todate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundOverviewStatistics(
        string $tag = NULL,
        string $fromdate = NULL,
        string $todate = NULL,
        string $messagestream = NULL): DynamicResponseModel
    {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound', $query));
	}

	/**
	 * Get send statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param string|null $tag Filter by tag.
	 * @param string|null $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $todate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundSendStatistics(
        string $tag = NULL,
        string $fromdate = NULL,
        string $todate = NULL,
        string $messagestream = NULL): DynamicResponseModel
    {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/sends', $query));
	}

	/**
	 * Get bounce statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param string|null $tag Filter by tag.
	 * @param string|null $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $todate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundBounceStatistics(
        string $tag = NULL,
        string $fromdate = NULL,
        string $todate = NULL,
        string $messagestream = NULL): DynamicResponseModel
    {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/bounces', $query));
	}

	/**
	 * Get SPAM complaint statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param string|null $tag Filter by tag.
	 * @param string|null $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $todate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundSpamComplaintStatistics(
        string $tag = NULL,
        string $fromdate = NULL,
        string $todate = NULL,
        string $messagestream = NULL): DynamicResponseModel
    {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/spam', $query));
	}

	/**
	 * Get bounce statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param string|null $tag Filter by tag.
	 * @param string|null $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $todate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundTrackedStatistics(
        string $tag = NULL,
        string $fromdate = NULL,
        string $todate = NULL,
        string $messagestream = NULL): DynamicResponseModel
    {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/tracked', $query));
	}

	/**
	 * Get open statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @param  string $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundOpenStatistics($tag = NULL, $fromdate = NULL, $todate = NULL, $messagestream = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/opens', $query));
	}

	/**
	 * Get platform statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param string|null $tag Filter by tag.
	 * @param string|null $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $todate  must be of the format 'YYYY-MM-DD'
	 * @param string|null $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundPlatformStatistics(
        string $tag = NULL,
        string $fromdate = NULL,
        string $todate = NULL,
        string $messagestream = NULL): DynamicResponseModel
    {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/opens/platforms', $query));
	}

	/**
	 * Get email client statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @param  string $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundEmailClientStatistics($tag = NULL, $fromdate = NULL, $todate = NULL, $messagestream = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/opens/emailclients', $query));
	}

	/**
	 * Get reading times for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @return DynamicResponseModel
	 */
	function getOutboundReadTimeStatistics($tag = NULL, $fromdate = NULL, $todate = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/opens/readtimes', $query));
	}

	/**
	 * Get click statistics for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @param  string $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundClickStatistics($tag = NULL, $fromdate = NULL, $todate = NULL, $messagestream = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/clicks', $query));
	}

	/**
	 * Get information about which browsers were used to open tracked links for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @param  string $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundClickBrowserFamilyStatistics($tag = NULL, $fromdate = NULL, $todate = NULL, $messagestream = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/clicks/browserfamilies', $query));
	}

	/**
	 * Get information about which browsers platforms (Desktop, Mobile, etc.) were used to open
	 * tracked links for the messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @param  string $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundClickBrowserPlatformStatistics($tag = NULL, $fromdate = NULL, $todate = NULL, $messagestream = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/clicks/platforms', $query));
	}

	/**
	 * Get information about part of the message (HTML or Text)
	 * tracked links were clicked from in messages sent using this Server,
	 * optionally filtering on message tag, and a to and from date.
	 *
	 * @param  string $tag Filter by tag.
	 * @param  string $fromdate  must be of the format 'YYYY-MM-DD'
	 * @param  string $todate  must be of the format 'YYYY-MM-DD'
	 * @param  string $messagestream Filter by Message Stream ID. If null, the default "outbound" transactional stream will be used.
	 * @return DynamicResponseModel
	 */
	function getOutboundClickLocationStatistics($tag = NULL, $fromdate = NULL, $todate = NULL, $messagestream = NULL) {
		$query = array();

		$query['tag'] = $tag;
		$query['fromdate'] = $fromdate;
		$query['todate'] = $todate;
		$query['messagestream'] = $messagestream;

		return new DynamicResponseModel((array)$this->processRestRequest('GET', '/stats/outbound/clicks/location', $query));
	}

	/**
	 * Create an Inbound Rule to block messages from a single email address, or an entire domain.
	 *
	 * @param  string $rule The email address (or domain) that will be blocked.
	 * @return PostmarkInboundRuleTrigger
	 */
	function createInboundRuleTrigger($rule): PostmarkInboundRuleTrigger
    {
		$body = array();
		$body["Rule"] = $rule;

		return new PostmarkInboundRuleTrigger((array)$this->processRestRequest('POST', '/triggers/inboundrules', $body));
	}

	/**
	 * Get a list of all existing Inbound Rule Triggers.
	 *
	 * @param integer $count The number of rule triggers to return with this request.
	 * @param integer $offset The number of triggers to 'skip' when 'paging' through rule triggers.
	 * @return PostmarkInboundRuleTriggerList
	 */
	function listInboundRuleTriggers(int $count = 100, int $offset = 0): PostmarkInboundRuleTriggerList
    {
		$query = array();

		$query["count"] = $count;
		$query["offset"] = $offset;

		return new PostmarkInboundRuleTriggerList((array)$this->processRestRequest('GET', '/triggers/inboundrules', $query));
	}

	/**
	 * Delete an Inbound Rule Trigger.
	 *
	 * @param integer $id The ID of the rule trigger we wish to delete.
	 * @return PostmarkResponse
	 */
	function deleteInboundRuleTrigger(int $id): PostmarkResponse
    {
		return new PostmarkResponse((array)$this->processRestRequest('DELETE', "/triggers/inboundrules/$id"));
	}

	/**
	 * Delete a template.
	 *
	 * @param string $id The ID or alias of the template to delete.
	 * @return PostmarkResponse
	 */
	function deleteTemplate(string $id): PostmarkResponse
    {
		return new PostmarkResponse((array)$this->processRestRequest('DELETE', "/templates/$id"));
	}

	/**
	 * Create a template
	 *
	 * @param string $name The friendly name for this template.
	 * @param string $subject The template to be used for the 'subject' of emails sent using this template.
	 * @param string $htmlBody The template to be used for the 'htmlBody' of emails sent using this template, optional if 'textBody' is not NULL.
	 * @param string $textBody The template to be used for the 'textBody' of emails sent using this template, optional if 'htmlBody' is not NULL.
	 * @param string|null $alias An optional string you can provide to identify this Template. Allowed characters are numbers, ASCII letters, and ‘.’, ‘-’, ‘_’ characters, and the string has to start with a letter.
	 * @param string $templateType Creates the template based on the template type provided. Possible options: Standard or Layout. Defaults to Standard.
	 * @param string|null $layoutTemplate The alias of the Layout template that you want to use as layout for this Standard template. If not provided, a standard template will not use a layout template.
	 *
	 * @return PostmarkTemplate
	 */
	function createTemplate(
        string $name,
        ?string $subject,
        ?string $htmlBody,
        ?string $textBody,
        ?string $alias = NULL,
        string $templateType = 'Standard',
        string $layoutTemplate = NULL): PostmarkTemplate
    {
		$template = array();
		$template["name"] = $name;
		$template["subject"] = $subject;
		$template["htmlBody"] = $htmlBody;
		$template["textBody"] = $textBody;
		$template["alias"] = $alias;
		$template["templateType"] = $templateType;
		$template["layoutTemplate"] = $layoutTemplate;

		return new PostmarkTemplate((array)$this->processRestRequest('POST', "/templates", $template));
	}

	/**
	 * Edit a template
	 *
	 * @param string $id The ID or alias of the template you wish to update.
	 * @param string|null $name The friendly name for this template.
	 * @param string|null $subject The template to be used for the 'subject' of emails sent using this template.
	 * @param string|null $htmlBody The template to be used for the 'htmlBody' of emails sent using this template.
	 * @param string|null $textBody The template to be used for the 'textBody' of emails sent using this template.
	 * @param string|null $alias An optional string you can provide to identify this Template. Allowed characters are numbers, ASCII letters, and ‘.’, ‘-’, ‘_’ characters, and the string has to start with a letter.
	 * @param string|null $layoutTemplate The alias of the Layout template that you want to use as layout for this Standard template. If not provided, a standard template will not use a layout template.
	 *
	 * @return PostmarkTemplate
	 */
	function editTemplate(
        string $id,
        string $name = NULL,
        string $subject = NULL,
        string $htmlBody = NULL,
        string $textBody = NULL,
        string $alias = NULL,
        string $layoutTemplate = NULL): PostmarkTemplate
    {
		$template = array();
		$template["name"] = $name;
		$template["subject"] = $subject;
		$template["htmlBody"] = $htmlBody;
		$template["textBody"] = $textBody;
		$template["alias"] = $alias;
		$template["layoutTemplate"] = $layoutTemplate;

		return new PostmarkTemplate((array)$this->processRestRequest('PUT', "/templates/$id", $template));
	}

    /**
     * Get the current information for a specific template.
     *
     * @param int $id the Id for the template info you wish to retrieve.
     * @return PostmarkTemplate
     */
	function getTemplate(int $id): PostmarkTemplate
    {
		return new PostmarkTemplate((array)$this->processRestRequest('GET', "/templates/$id"));
	}

    /**
     * Get the current information for a specific template.
     *
     * @param string $alias the alias for the template info you wish to retrieve.
     * @return PostmarkTemplate
     */
    function getTemplateByAlias(string $alias): PostmarkTemplate
    {
        return new PostmarkTemplate((array)$this->processRestRequest('GET', "/templates/$alias"));
    }

	/**
	 * Get all templates associated with the Server.
	 *
	 * @param integer $count The total number of templates to get at once (default is 100)
	 * @param integer $offset The number of templates to "Skip" before returning results.
	 * @param string $templateType Filters the results based on the template type provided. Possible options: Standard, Layout, All. Defaults to All.
	 * @param string|null $layoutTemplate Filters the results based on the layout template alias. Defaults to NULL.
	 *
	 * @return PostmarkTemplateList
	 */
	function listTemplates(
        int $count = 100,
        int $offset = 0,
        string $templateType = 'All',
        string $layoutTemplate = NULL): PostmarkTemplateList
    {
		$query = array();

		$query["count"] = $count;
		$query["offset"] = $offset;
		$query["templateType"] = $templateType;
		$query["layoutTemplate"] = $layoutTemplate;

		return new PostmarkTemplateList((array)$this->processRestRequest('GET', "/templates", $query));
	}

	/**
	 * Confirm that your template content can be parsed/rendered, get a test rendering of your template, and a suggested model to use with your templates.
	 *
	 * @param string|null $subject The Subject template you wish to test.
	 * @param string|null $htmlBody The HTML template you wish to test
	 * @param string|null $textBody The number of templates to "Skip" before returning results.
	 * @param object|null $testRenderModel The model to be used when doing test renders of the templates that successfully parse in this request.
	 * @param bool $inlineCssForHtmlTestRender If htmlBody is specified, the test render will automatically do CSS Inlining for the HTML content. You may opt-out of this behavior by passing 'false' for this parameter.
	 * @param string $templateType Validates templates based on template type (layout template or standard template). Possible options: Standard or Layout. Defaults to Standard.
	 * @param string|null $layoutTemplate An optional string to specify which layout template alias to use to validate a standard template. If not provided a standard template will not use a layout template.
	 * @return DynamicResponseModel
	 */
	function validateTemplate(
        string $subject = NULL,
        string $htmlBody = NULL,
        string $textBody = NULL,
        object $testRenderModel = NULL,
        bool $inlineCssForHtmlTestRender = true,
        string $templateType = 'Standard',
        string $layoutTemplate = NULL): DynamicResponseModel
    {
		$query = array();

		$query["subject"] = $subject;
		$query["htmlBody"] = $htmlBody;
		$query["textBody"] = $textBody;
		$query["testRenderModel"] = $testRenderModel;
		$query["inlineCssForHtmlTestRender"] = $inlineCssForHtmlTestRender;
		$query["templateType"] = $templateType;
		$query["layoutTemplate"] = $layoutTemplate;

		return new DynamicResponseModel((array)$this->processRestRequest('POST', "/templates/validate", $query));
	}

    /**
     * Get information about a specific webhook configuration.
     *
     * @param int $id The Id of the webhook configuration you wish to retrieve.
     * @return WebhookConfiguration
     */
	function getWebhookConfiguration(int $id): WebhookConfiguration
    {
		return new WebhookConfiguration((array)$this->processRestRequest('GET', "/webhooks/$id"));
	}

	/**
	 * Get all webhook configurations associated with the Server.
	 *
	 * @param string|null $messageStream Optional message stream to filter results by. If not provided, all configurations for the server will be returned.
	 * @return WebhookConfigurationListingResponse
	 */
	function getWebhookConfigurations(string $messageStream = NULL): WebhookConfigurationListingResponse
    {
		$query = array();
		$query["MessageStream"] = $messageStream;

		return new WebhookConfigurationListingResponse((array)$this->processRestRequest('GET', "/webhooks", $query));
	}

	/**
	 * Delete a webhook configuration.
	 *
	 * @param integer $id The Id of the webhook configuration you wish to delete.
	 * @return PostmarkResponse
	 */
	function deleteWebhookConfiguration(int $id): PostmarkResponse
    {
		return new PostmarkResponse((array)$this->processRestRequest('DELETE', "/webhooks/$id"));
	}

	/**
	 * Create a webhook configuration.
	 *
	 * @param string $url The webhook URL.
	 * @param string|null $messageStream Message stream this configuration should belong to. If not provided, it will belong to the default transactional stream.
	 * @param object|null $httpAuth Optional Basic HTTP Authentication.
	 * @param array|null $httpHeaders Optional list of custom HTTP headers.
	 * @param object|null $triggers Optional triggers for this webhook configuration.
	 *
	 * @return WebhookConfiguration
	 */
	function
    createWebhookConfiguration(
        string $url,
        string $messageStream = NULL,
        object $httpAuth = NULL,
        array $httpHeaders = NULL,
        object $triggers = NULL): WebhookConfiguration
	{
		$body = array();
		$body["Url"] = $url;
		$body["MessageStream"] = $messageStream;
		$body["HttpAuth"] = $httpAuth;
		$body["HttpHeaders"] = $this->fixHeaders($httpHeaders);
		$body["Triggers"] = $triggers;

		return new WebhookConfiguration((array)$this->processRestRequest('POST', "/webhooks", $body));
	}

	/**
	 * Edit a webhook configuration.
	 * Any parameters passed with NULL will be ignored (their existing values will not be modified).
	 *
	 * @param integer $id The Id of the webhook configuration you wish to edit.
	 * @param string|null $url Optional webhook URL.
	 * @param object|null $httpAuth Optional Basic HTTP Authentication.
	 * @param array|null $httpHeaders Optional list of custom HTTP headers.
	 * @param object|null $triggers Optional triggers for this webhook configuration.
	 *
	 * @return WebhookConfiguration
	 */
	function editWebhookConfiguration(
        int $id,
        string $url = NULL,
        object $httpAuth = NULL,
        array $httpHeaders = NULL,
        object $triggers = NULL): WebhookConfiguration
    {
		$body = array();
		$body["Url"] = $url;
		$body["HttpAuth"] = $httpAuth;
		$body["HttpHeaders"] = $this->fixHeaders($httpHeaders);
		$body["Triggers"] = $triggers;

		return new WebhookConfiguration((array)$this->processRestRequest('PUT', "/webhooks/$id", $body));
	}

	/**
	 * Create Suppressions for the specified recipients.
	 *
	 * @param array|string $suppressionChanges Array of SuppressionChangeRequest objects that specify what recipients to suppress.
	 * @param string|null $messageStream Message stream where the recipients should be suppressed. If not provided, they will be suppressed on the default transactional stream.
	 *
	 * Suppressions will be generated with a Customer Origin and will have a ManualSuppression reason.
	 * @return PostmarkSuppressionResultList
	 */
	function createSuppressions(array|string $suppressionChanges = array(), string $messageStream = NULL): PostmarkSuppressionResultList
	{
		$body = array();
		$body["Suppressions"] = $suppressionChanges;

		if ($messageStream === NULL) {
			$messageStream = "outbound";
		}

		return new PostmarkSuppressionResultList((array)$this->processRestRequest('POST', "/message-streams/$messageStream/suppressions", $body));
	}

	/**
	 * Reactivate Suppressions for the specified recipients.
	 *
	 * @param array|string $suppressionChanges Array of SuppressionChangeRequest objects that specify what recipients to reactivate.
	 * @param string|null $messageStream Message stream where the recipients should be reactivated. If not provided, they will be reactivated on the default transactional stream.
	 *
	 * Only 'Customer' origin 'ManualSuppression' suppressions and 'Recipient' origin 'HardBounce' suppressions can be reactivated.
	 * @return PostmarkSuppressionResultList
	 */
	function deleteSuppressions(array|string $suppressionChanges = array(), string $messageStream = NULL): PostmarkSuppressionResultList
	{
		$body = array();
		$body["Suppressions"] = $suppressionChanges;

		if ($messageStream === NULL) {
			$messageStream = "outbound";
		}

		return new PostmarkSuppressionResultList((array)$this->processRestRequest('POST', "/message-streams/$messageStream/suppressions/delete", $body));
	}

	/**
	 * List Suppressions that match the provided query parameters.
	 *
	 * @param string|null $messageStream Filter Suppressions by MessageStream. If not provided, Suppressions for the default transactional stream will be returned. (optional)
	 * @param string|null $suppressionReason Filter Suppressions by reason. E.g.: HardBounce, SpamComplaint, ManualSuppression. (optional)
	 * @param string|null $origin Filter Suppressions by the origin that created them. E.g.: Customer, Recipient, Admin. (optional)
	 * @param string|null $fromDate Filter suppressions from the date specified - inclusive. (optional)
	 * @param string|null $toDate Filter suppressions up to the date specified - inclusive. (optional)
	 * @param string|null $emailAddress Filter by a specific email address. (optional)
	 *
	 * @return PostmarkSuppressionResultList
	 */
	function getSuppressions(
        string $messageStream = NULL,
        string $suppressionReason = NULL,
        string $origin = NULL,
        string $fromDate = NULL,
        string $toDate = NULL,
        string $emailAddress = NULL): PostmarkSuppressionResultList
    {
		$query = array();
		$query["SuppressionReason"] = $suppressionReason;
		$query["Origin"] = $origin;
		$query["FromDate"] = $fromDate;
		$query["ToDate"] = $toDate;
		$query["EmailAddress"] = $emailAddress;

		if ($messageStream === NULL) {
			$messageStream = "outbound";
		}

		return new PostmarkSuppressionResultList((array)$this->processRestRequest('GET', "/message-streams/$messageStream/suppressions/dump", $query));
	}

	/**
	 * Create a new message stream on your server.
	 *
	 * @param string $id Identifier for your message stream, unique at server level.
	 * @param string $messageStreamType Type of the message stream. Possible values: ["Transactional", "Inbound", "Broadcasts"].
	 * @param string $name Friendly name for your message stream.
	 * @param string|null $description Friendly description for your message stream. (optional)
	 *
	 * Currently, you cannot create multiple inbound streams.
	 * @return PostmarkMessageStream
	 */
	function createMessageStream(string $id, string $messageStreamType, string $name, string $description = NULL): PostmarkMessageStream
    {
		$body = array();
		$body["ID"] = $id;
		$body["MessageStreamType"] = $messageStreamType;
		$body["Name"] = $name;
		$body["Description"] = $description;

		return new PostmarkMessageStream((array)$this->processRestRequest('POST', "/message-streams", $body));
	}

	/**
	 * Edit the properties of a message stream.
	 *
	 * @param string $id The identifier for the stream you are trying to update.
	 * @param string|null $name New friendly name to use. (optional)
	 * @param string|null $description New description to use. (optional)
	 *
	 * @return PostmarkMessageStream
	 */
	function editMessageStream(string $id, string $name = NULL, string $description = NULL): PostmarkMessageStream
    {
		$body = array();
		$body["Name"] = $name;
		$body["Description"] = $description;

		return new PostmarkMessageStream((array)$this->processRestRequest('PATCH', "/message-streams/$id", $body));
	}

	/**
	 * Retrieve details about a message stream.
	 *
	 * @param string $id Identifier of the stream to retrieve details for.
	 *
	 * @return PostmarkMessageStream
	 */
	function getMessageStream(string $id): PostmarkMessageStream
    {
		return new PostmarkMessageStream((array)$this->processRestRequest('GET', "/message-streams/$id"));
	}

	/**
	 * Retrieve all message streams on the server.
	 *
	 * @param string $messageStreamType Filter by stream type. Possible values: ["Transactional", "Inbound", "Broadcasts", "All"]. Defaults to: All.
	 * @param string $includeArchivedStreams Include archived streams in the result. Defaults to: false.
	 *
	 * @return PostmarkMessageStreamList
	 */
	function listMessageStreams(string $messageStreamType = 'All', string $includeArchivedStreams = 'false'): PostmarkMessageStreamList
    {
		$query = array();
		$query["MessageStreamType"] = $messageStreamType;
		$query["IncludeArchivedStreams"] = $includeArchivedStreams;

		return new PostmarkMessageStreamList((array)$this->processRestRequest('GET', "/message-streams", $query));
	}

	/**
	 * Archive a message stream. This will disable sending/receiving messages via that stream.
	 * The stream will also stop being shown in the Postmark UI.
	 * Once a stream has been archived, it will be deleted (alongside associated data) at the ExpectedPurgeDate in the response.
	 *
	 * @param string $id The identifier for the stream you are trying to update.
	 *
	 * @return PostmarkMessageStreamArchivalConfirmation
	 */
	function archiveMessageStream(string $id): PostmarkMessageStreamArchivalConfirmation
    {
		return new PostmarkMessageStreamArchivalConfirmation((array)$this->processRestRequest('POST', "/message-streams/$id/archive"));
	}

	/**
	 * Unarchive a message stream. This will resume sending/receiving via that stream.
	 * The stream will also re-appear in the Postmark UI.
	 * A stream can be unarchived only before the stream ExpectedPurgeDate.
	 *
	 * @param string $id Identifier of the stream to unarchive.
	 *
	 * @return PostmarkMessageStream
	 */
	function unarchiveMessageStream(string $id): PostmarkMessageStream
    {
		return new PostmarkMessageStream((array)$this->processRestRequest('POST', "/message-streams/$id/unarchive"));
	}
}

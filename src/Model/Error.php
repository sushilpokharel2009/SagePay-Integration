<?php namespace Academe\SagePay\Psr7\Model;

/**
 * Value object to hold an error, returned by SagePay when posting a transaction.
 * Multiple validation errors will be returned when the HTTP return code is 422.
 * These will be held by the ErrorCollection class. Examples of 422 code errors are:
 *  1003    Missing mandatory field
 *  1004    Invalid length
 *  1005    Contains invalid characters
 *  1007    The card number has failed our validity checks and is invalid
 *  1008    The card is not supported
 *  1009    Contains invalid value
 * The 1XXX numbers are the SagePay erro codes. These will each include a property
 * name as they are targetted at specific fields that fail validation.
 *
 * Other ~400 return codes will return just one error in the body, without a property
 * as they are not targetted as specific fields.
 */

use Academe\SagePay\Psr7\Helper;

class Error
{
    /**
     * @var
     */
    protected $code;
    protected $description;
    protected $property;
    protected $clientMessage;
    protected $httpCode;

    /**
     * @param string|int $code The error code supplied by the remote API
     * @param string $description The textual detail of the error
     * @param string|null $property The property name (field name) of the property the error applies to
     * @param string|null $clientMessage
     * @param string|null $httpCode
     */
    public function __construct($code, $description, $property = null, $clientMessage = null, $httpCode = null)
    {
        $this->code = $code;
        $this->description = $description;
        $this->property = $property;
        $this->clientMessage = $clientMessage;
        $this->httpCode = $httpCode;
    }

    /**
     * @return int|string The error code supplied by the remote API
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string The textual detail of the error
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return null|string The property name (field name) of the property the error applies to
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return null|string The end-person presentable message associated with some validation errors
     */
    public function getClientMessage()
    {
        return $this->clientMessage;
    }

    /**
     * @return int|string The HTTP code associated with the error, if available
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * The statusCode and statusDetail is a legacy error format that seems to have crept
     * into some validation errors. If this is a long-term "feature" of the API, then
     * it may be worth translating some of the errors. For example statusCode 3123
     * is "The DeliveryAddress1 value is too long". This translates to code 1004 (Invalid length)
     * for the property "shippingDetails.shippingAddress1". Ideally we should not have
     * to do that.
     */

    /**
     * @param array|object $data Error data from the API to initialise the Error object
     *
     * @param null $httpCode
     * @return static New instance of Error object
     */
    public static function fromData($data, $httpCode = null)
    {
        if ($data instanceof Error) {
            return $data;
        }

        // Some errors have a "code" and some have a "statusCode". They amount to
        // the same thing. See list of codes here:
        // https://github.com/academe/SagePay/blob/master/src/Academe/SagePay/Metadata/error-codes.tsv

        $code = Helper::dataGet($data, 'code',
            Helper::dataGet($data, 'statusCode', $httpCode)
        );

        $description = Helper::dataGet($data, 'description',
            Helper::dataGet($data, 'statusDetail', null)
        );

        // If the error refers to form validation problems, then $property will point
        // to the field in error and $clientMessage will provide a message suitable
        // to put in front of the end user.
        // These two fields will only be provided when the HTTP code is 422. However,
        // even with a HTTP code of 422, these fields may not be provided.
        //
        // The history to this is that the Sage Pay REST API is bolted on top of the
        // Sage Pay Direct API. That underlying API can NOT return multiple errors,
        // point to a specific field or provide error messages that can be put in front
        // of the end user. Those underlying errors are pass out through the REST API
        // as they come. The REST API may generate some of its own errors before the data
        // gets passed on to the underlying Sage Pay Direct API, and being newer, is able
        // to provide multiple errors for multiple fields at the same time, and more useful
        // error messages and error metadata. The hope is that more validation is done in
        // the REST API layer as time goes on, so we get am improvement in the way errors
        // are handled. In the meantime it is, frankly, a bit of an inconsistent mess so
        // here we try to normalise the various error formats into one object.
        // We can, as an interim measure, provide a lookup table from code (statusCode)
        // to field names to fill in the property and client message ourselves.
        // For example, code 5055 is the error "A Postcode field contains invalid characters."
        // That particular message is probably safe for the end user. The code 5055 can
        // map onto a property of "billingAddress.postalCode", to follow the convention
        // that the REST API appears to be using. We shouldn't have to do this, but to
        // improve the UX, this data is necessary.

        $property = Helper::dataGet($data, 'property', null);
        $clientMessage = Helper::dataGet($data, 'clientMessage', null);

        return new static($code, $description, $property, $clientMessage, $httpCode);
    }
}

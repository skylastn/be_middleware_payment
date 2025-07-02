<?php

namespace App\Models\Response\Socket;

use Exception;
use stdClass;

class SocketMiddlewareResponse
{
    private ?bool $status; // json:status Optional
    private ?string $message; // json:message Optional
    private ?string $data; // json:data Optional

    /**
     * @param bool|null $status
     * @param string|null $message
     * @param string|null $data
     */
    public function __construct(?bool $status, ?string $message, ?string $data)
    {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * @param ?bool $value
     * @throws Exception
     * @return ?bool
     */
    public static function fromStatus(?bool $value): ?bool
    {
        if (!is_null($value)) {
            return $value; /*bool*/
        } else {
            return null;
        }
    }

    /**
     * @throws Exception
     * @return ?bool
     */
    public function toStatus(): ?bool
    {
        if (SocketMiddlewareResponse::validateStatus($this->status)) {
            if (!is_null($this->status)) {
                return $this->status; /*bool*/
            } else {
                return  null;
            }
        }
        throw new Exception('never get to this SocketMiddlewareResponse::status');
    }

    /**
     * @param bool|null
     * @return bool
     * @throws Exception
     */
    public static function validateStatus(?bool $value): bool
    {
        if (!is_null($value)) {
            if (!is_bool($value)) {
                throw new Exception("Attribute Error:SocketMiddlewareResponse::status");
            }
        }
        return true;
    }

    /**
     * @return ?bool
     */
    public function getStatus(): ?bool
    {
        return $this->status;
    }

    /**
     * @param bool|null
     * @throws Exception
     */
    public function setStatus(?bool $value)
    {
        if (SocketMiddlewareResponse::validateStatus($value)) {
            $this->status = $value;
        }
    }

    /**
     * @return ?bool
     */
    public static function sampleStatus(): ?bool
    {
        return true; /*31:status*/
    }

    /**
     * @param ?string $value
     * @throws Exception
     * @return ?string
     */
    public static function fromMessage(?string $value): ?string
    {
        if (!is_null($value)) {
            return $value; /*string*/
        } else {
            return null;
        }
    }

    /**
     * @throws Exception
     * @return ?string
     */
    public function toMessage(): ?string
    {
        if (SocketMiddlewareResponse::validateMessage($this->message)) {
            if (!is_null($this->message)) {
                return $this->message; /*string*/
            } else {
                return  null;
            }
        }
        throw new Exception('never get to this SocketMiddlewareResponse::message');
    }

    /**
     * @param string|null
     * @return bool
     * @throws Exception
     */
    public static function validateMessage(?string $value): bool
    {
        if (!is_null($value)) {
            if (!is_string($value)) {
                throw new Exception("Attribute Error:SocketMiddlewareResponse::message");
            }
        }
        return true;
    }

    /**
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string|null
     * @throws Exception
     */
    public function setMessage(?string $value)
    {
        if (SocketMiddlewareResponse::validateMessage($value)) {
            $this->message = $value;
        }
    }

    /**
     * @return ?string
     */
    public static function sampleMessage(): ?string
    {
        return 'SocketMiddlewareResponse::message::32'; /*32:message*/
    }

    /**
     * @param ?string $value
     * @throws Exception
     * @return ?string
     */
    public static function fromData(?string $value): ?string
    {
        if (!is_null($value)) {
            return $value; /*string*/
        } else {
            return null;
        }
    }

    /**
     * @throws Exception
     * @return ?string
     */
    public function toData(): ?string
    {
        if (SocketMiddlewareResponse::validateData($this->data)) {
            if (!is_null($this->data)) {
                return $this->data; /*string*/
            } else {
                return  null;
            }
        }
        throw new Exception('never get to this SocketMiddlewareResponse::data');
    }

    /**
     * @param string|null
     * @return bool
     * @throws Exception
     */
    public static function validateData(?string $value): bool
    {
        if (!is_null($value)) {
            if (!is_string($value)) {
                throw new Exception("Attribute Error:SocketMiddlewareResponse::data");
            }
        }
        return true;
    }

    /**
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * @param string|null
     * @throws Exception
     */
    public function setData(?string $value)
    {
        if (SocketMiddlewareResponse::validateData($value)) {
            $this->data = $value;
        }
    }

    /**
     * @return ?string
     */
    public static function sampleData(): ?string
    {
        return 'SocketMiddlewareResponse::data::33'; /*33:data*/
    }

    /**
     * @throws Exception
     * @return bool
     */
    public function validate(): bool
    {
        return SocketMiddlewareResponse::validateStatus($this->status)
            || SocketMiddlewareResponse::validateMessage($this->message)
            || SocketMiddlewareResponse::validateData($this->data);
    }

    /**
     * @return stdClass
     * @throws Exception
     */
    public function to(): stdClass
    {
        $out = new stdClass();
        $out->status = $this->toStatus();
        $out->message = $this->toMessage();
        $out->data = $this->toData();
        return $out;
    }

    /**
     * @param stdClass $obj
     * @return SocketMiddlewareResponse
     * @throws Exception
     */
    public static function from(stdClass $obj): SocketMiddlewareResponse
    {
        SocketMiddlewareResponse::validateStatus($obj->status, true);
        SocketMiddlewareResponse::validateMessage($obj->message, true);
        SocketMiddlewareResponse::validateData($obj->data, true);
        return new SocketMiddlewareResponse(
            SocketMiddlewareResponse::fromStatus($obj->status),
            SocketMiddlewareResponse::fromMessage($obj->message),
            SocketMiddlewareResponse::fromData($obj->data)
        );
    }

    /**
     * @return SocketMiddlewareResponse
     */
    public static function sample(): SocketMiddlewareResponse
    {
        return new SocketMiddlewareResponse(
            SocketMiddlewareResponse::sampleStatus(),
            SocketMiddlewareResponse::sampleMessage(),
            SocketMiddlewareResponse::sampleData()
        );
    }
}

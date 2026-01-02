<?php

namespace Saulmoralespa\Alegra;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Utils;

class Client
{
    const URL_BASE = 'https://api.alegra.com/api/v1/';

    public function __construct(
        private $user,
        private $pass
    )
    {
    }

    public function client(): GuzzleClient
    {
        return new GuzzleClient([
            "base_uri" => self::URL_BASE
        ]);
    }

    public function invoices(array $query = []): array
    {
        try {
            $response = $this->client()->get("invoices", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function createInvoice(array $data): array
    {
        try {
            $response = $this->client()->post("invoices", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getInvoice(int $id, $query = []): array
    {
        try {
            $response = $this->client()->get("invoices/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message, $exception->getCode());
        }
    }

    public function editInvoice(int $id, array $data): array
    {
        try {
            $response = $this->client()->put("invoices/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deleteInvoice(int $id): array
    {
        try {
            $response = $this->client()->delete("invoices/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function sendInvoiceByEmail(int $id, array $data): array
    {
        try {
            $response = $this->client()->post("invoices/$id/email", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function voidInvoice(int $id, array $data = []):array
    {
        try {
            $response = $this->client()->post("invoices/$id/void", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function stampInvoices(array $ids):array
    {
        try {
            $response = $this->client()->post("invoices/stamp", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => [
                    "ids" => $ids
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getPayments($query = []):array
    {
        try {
            $response = $this->client()->get("payments", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function createPayment(array $data = []):array
    {
        try {
            $response = $this->client()->post("invoices/payments", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getPayment(int $id, $query = []):array
    {
        try {
            $response = $this->client()->get("payments/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function editPayment(int $id, array $data):array
    {
        try {
            $response = $this->client()->put("payments/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deletePayment(int $id): array
    {
        try {
            $response = $this->client()->delete("payments/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function voidPayment(int $id):array
    {
        try {
            $response = $this->client()->post("payments/$id/void", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }


    public function getContacts($query = []):array
    {
        try {
            $response = $this->client()->get("contacts", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function createContact(array $data):array
    {
        try {
            $response = $this->client()->post("contacts", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getContact(int $id, array $query = []):array
    {
        try {
            $response = $this->client()->get("contacts/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function editContact(int $id, array $data):array
    {
        try {
            $response = $this->client()->put("contacts/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deleteContact(int $id):array
    {
        try {
            $response = $this->client()->delete("contacts/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deleteContacts(array $ids):array
    {
        try {
            $ids = implode(',', $ids);
            $response = $this->client()->delete("contacts?id=$ids", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function createSeller(array $data):array
    {
        try {
            $response = $this->client()->post("sellers", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getCostCenters($query = []):array
    {
        try {
            $response = $this->client()->get("cost-centers", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getSellers($query = []):array
    {
        try {
            $response = $this->client()->get("sellers", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getSeller(int $id):array
    {
        try {
            $response = $this->client()->get("sellers/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function editSeller(int $id, $data):array
    {
        try {
            $response = $this->client()->put("sellers/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deleteSeller(int $id):array
    {
        try {
            $response = $this->client()->delete("sellers/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getItems($query = []):array
    {
        try {
            $response = $this->client()->get("items", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getItem(int $id, array $query = []):array
    {
        try {
            $response = $this->client()->get("items/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function createItem(array $data):array
    {
        try {
            $response = $this->client()->post("items", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function editItem(int $id, array $data):array
    {
        try {
            $response = $this->client()->put("items/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deleteItem(int $id): array
    {
        try {
            $response = $this->client()->delete("items/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getWarehouses($query = []):array
    {
        try {
            $response = $this->client()->get("warehouses", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function createWarehouse(array $data):array
    {
        try {
            $response = $this->client()->post("warehouses", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getWarehouse(int $id)
    {
        try {
            $response = $this->client()->get("warehouses/$id", [
                "headers" => [
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function editWarehouse(int $id, array $data):array
    {
        try {
            $response = $this->client()->put("warehouses/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "json" => $data
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function deleteWarehouse(int $id):array
    {
        try {
            $response = $this->client()->delete("warehouses/$id", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ]
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }

    public function getTaxes($query = []):array
    {
        try {
            $response = $this->client()->get("taxes", [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode("$this->user:$this->pass")
                ],
                "query" => $query
            ]);
            return self::responseJson($response->getBody()->getContents());
        }catch (RequestException $exception){
            $message = self::handleErrors($exception);
            throw new \Exception($message);
        }
    }


    public static function responseJson(string $response):array
    {
        return Utils::jsonDecode($response, true);
    }

    protected static function handleErrors($exception): string
    {
        $jsonResponse = $exception->getResponse()->getBody()->getContents();
        $response = self::responseJson($jsonResponse);
        $message = $response['message'] ?? "";
        $message .= isset($response['code'])  ? " {$response['code']}"  : " {$exception->getCode()}";

        return $message;
    }
}
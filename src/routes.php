<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Firebase\JWT\JWT;
use Tuupola\Base62;

// Login
$app->post("/api/login", function ($request, $response, $arguments) {
    $this->logger->info("login start");
    $username = $request->getParsedBodyParam('username', $default = null);
    $password = $request->getParsedBodyParam('password', $default = null);
    $connection = connect_db();
    $result = $connection->query("SELECT * FROM user WHERE username='$username' AND password='$password'");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $this->logger->info("[$username] login success");
            // generate JWT
            $now = new DateTime();
            $future = new DateTime("now +2 hours");
            $server = $request->getServerParams();
            $jti = (new Base62)->encode(mt_rand(16,16));
            $payload = [
                "iat" => $now->getTimeStamp(),
                //"exp" => $future->getTimeStamp(),
                "jti" => $jti,
                "userId" => $row['id']
            ];
            $secret = $this->get('settings')['secret'];
            $token = JWT::encode($payload, $secret, "HS256");
            $data["token"] = $token;
            //$data["expires"] = $future->getTimeStamp();
            return $response->withStatus(201)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    } else {
        $this->logger->info("[$username]login fail");
        return $response->withStatus(401);
    }
});

// Get all verify attempts
$app->get("/api/get/verify/attempts", function ($request, $response, $arguments) {
    $this->logger->info("get verify attempts start");
    $connection = connect_db();
    $query = "SELECT * FROM verify_attempt ORDER BY last_attempt_date DESC";
    $result = $connection->query($query) or die($this->logger->error("Fail to get verify attempt. Error[". json_encode($connection->error)."]"));
    
    $data = [];
    $i = 0;
    while($row = $result->fetch_assoc()) {
        $data[$i++] = $row;
    }
    $payload["count"] = $result->num_rows;
    $payload["data"] = $data;
    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

// Get verify attempt by purchase code
$app->get("/api/get/verify/attempt", function ($request, $response, $arguments) {
    $purchaseCode = $request->getQueryParam('purchaseCode', $default = null);
    $this->logger->info("get verify attempt by purchaseCode[$purchaseCode]");
    $connection = connect_db();
    $query = "SELECT * FROM verify_attempt WHERE purchase_code='$purchaseCode'";
    $result = $connection->query($query) or die($this->logger->error("Fail to get verify attempt. Error[". json_encode($connection->error)."]"));
    
    if ($result->num_rows > 0) {
        $payload = $result->fetch_assoc();
        return $response->withStatus(200)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    } else {
        return $response->withStatus(404);
    }
});

// Update verify tries
$app->post("/api/update/verify/tries", function ($request, $response, $arguments) {
    $purchaseCode = $request->getParsedBodyParam('purchaseCode', $default = null);
    $tries = $request->getParsedBodyParam('tries', $default = null);
    $this->logger->info("purchaseCode[$purchaseCode] update verify tries[$tries]");
    if ($purchaseCode==null || $purchaseCode=='' || $tries==null || $tries=='') {
        $this->logger->error("purchaseCode[$purchaseCode] Invalid value to update verify, tries[$tries]");
        return $response->withStatus(500);
    }
    $connection = connect_db();
    $query = "UPDATE verify_attempt SET total_tries=$tries, updated_date=now() WHERE purchase_code='$purchaseCode'";
    $result = $connection->query($query);
    if ($result === TRUE) {
        $this->logger->info("purchaseCode[$purchaseCode] Tries updated to $tries");
        return $response->withStatus(200);
    } else {
        $this->logger->error("purchaseCode[$purchaseCode] Fail to update tries. Error[". json_encode($connection->error)."]");
        return $response->withStatus(500);
    }
});

// Delete verify attempts
$app->post("/api/delete/verify/attempts", function ($request, $response, $arguments) {
    $purchaseCode = $request->getParsedBodyParam('purchaseCode', $default = null);
    $this->logger->info("purchaseCode[$purchaseCode] update verify attempts");
    if ($purchaseCode==null || $purchaseCode=='') {
        $this->logger->error("purchaseCode[$purchaseCode] Invalid value to delete verify attempts");
        return $response->withStatus(500);
    }
    $connection = connect_db();
    $query = "DELETE FROM verify_attempt WHERE purchase_code='$purchaseCode'";
    $result = $connection->query($query);
    if ($result === TRUE) {
        $this->logger->info("purchaseCode[$purchaseCode] Verify attempt deleted successfully!");
        return $response->withStatus(200);
    } else {
        $this->logger->error("purchaseCode[$purchaseCode] Fail to delete verify attempts. Error[". json_encode($connection->error)."]");
        return $response->withStatus(500);
    }
});

// public APIs

$app->get("/api/public/verify/purchase", function ($request, $response, $arguments) {
    $author = $request->getQueryParam('author', $default = null);
    $item = $request->getQueryParam('item', $default = null);
    $token = $request->getQueryParam('token', $default = null);
    $purchaseCode = $request->getQueryParam('purchaseCode', $default = null);
    $this->logger->info("verify purchase code start. author[$author], item[$item], token[$token], purchaseCode[$purchaseCode]");

    $url = "https://api.envato.com/v3/market/author/sale?code=".$purchaseCode;
    $headers = array('Authorization' => 'Bearer '.$token);

    Unirest\Request::verifyPeer(false);
    $envatoResponse = Unirest\Request::get($url, $headers, null);
    $this->logger->info("Envato Response [". json_encode($envatoResponse)."]");

    /* -1=fail, 1=success, 2=retry expire*/
    $returnValue = "-1";
    if ($envatoResponse->code!=200) {
        $returnValue = "-1";
    } else {
        $envatoResponseBody = $envatoResponse->body;
        if (isset($envatoResponseBody->item->name) && isset($envatoResponseBody->item->author_username)) {
            if ($envatoResponseBody->item->name==$item && $envatoResponseBody->item->author_username==$author) {

                // check attempt
                $connection = connect_db();
                $result = $connection->query("SELECT * FROM verify_attempt WHERE purchase_code='$purchaseCode'") 
                        or die($this->logger->error("purchaseCode[$purchaseCode] Fail to query verify attempt. Error[". json_encode($connection->error)."]"));
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        // not first attempt
                        // check if total attempts is equals to total tries allowed
                        if ($row['total_attempt']>=$row['total_tries']) {
                            $this->logger->info("purchaseCode[$purchaseCode] Retries expire! totalTries[".$row['total_tries']."], totalAttempt[".$row['total_attempt']."]");
                            $returnValue = "2";
                        } else {
                            // tries not expire
                            $query = "UPDATE verify_attempt SET total_attempt=total_attempt+1, last_attempt_date=now()
                                    WHERE purchase_code='$purchaseCode'";
                            $result2 = $connection->query($query);
                            if ($result2 === TRUE) {
                                $this->logger->info("purchaseCode[$purchaseCode] Attempt updated successfully!");
                            } else {
                                $this->logger->error("purchaseCode[$purchaseCode] Fail to update attempt. Error[". json_encode($connection->error)."]");
                            }
                            $returnValue = "1";
                        }
                    }
                } else {
                    // first attempt
                    $buyerName = "";
                    if (isset($envatoResponseBody->buyer)) {
                        $buyerName = $envatoResponseBody->buyer;
                        $this->logger->info("Envato Response buyerName[$buyerName]");
                    }
                    $totalTries = 3;
                    $query = "INSERT INTO 
                            verify_attempt(purchase_code, buyer_name, total_tries, total_attempt, 
                            last_attempt_date, created_date, updated_date) 
                            VALUES ('$purchaseCode', '$buyerName', $totalTries, 1, now(), now(), null)";
                    $result = $connection->query($query);
                    if ($result === TRUE) {
                        $this->logger->info("purchaseCode[$purchaseCode] First attempt inserted successfully!");
                    } else {
                        $this->logger->error("purchaseCode[$purchaseCode] Fail to insert first attempt. Error[". json_encode($connection->error)."]");
                    }
                    $returnValue = "1";
                }
            } else {
                $returnValue = "-1";
            }
        } else {
            $returnValue = "-1";
        }
    }
    return $response->withStatus(200)
            ->write($returnValue);
});

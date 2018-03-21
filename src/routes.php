<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Firebase\JWT\JWT;
use Tuupola\Base62;

// Routes

$app->get('/api/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

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

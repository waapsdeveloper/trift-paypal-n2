<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srmklive\PayPal\Facades\PayPal as PayPalFacade;
use Srmklive\PayPal\Facades\PayPal; // Correct PayPal Facade
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Support\Str;


// Correct PayPal Facade

class PayPalController extends Controller
{
    private $provider;

    public function __construct(PayPalFacade $paypal)
    {
        $this->provider = $paypal;
    }

    // Create Payment Method
    public function createPayment(Request $request)
    {
        $request->validate([
            'card_number' => 'required|digits:16',
            'expiry_month' => 'required|digits:2',
            'expiry_year' => 'required|digits:4',
            'cvv' => 'required|digits:3',
            'amount' => 'required|numeric|min:0.01',
        ]);


        $provider = new PayPalClient(); // Direct initialization without facade
        // dd(config('paypal'));

        $accessToken = $provider->getAccessToken();
        // dd($accessToken);
        $provider->setApiCredentials(config('paypal')); // Load credentials from config

        // Payment data
        $data = [
            'intent' => 'CAPTURE', // CAPTURE for immediate payment
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => $request->amount, // Use input amount
                    ],
                    'description' => 'Service Payment',
                ],
            ],
            'application_context' => [
                'return_url' => route('paypal.status'), // Redirect URL after approval
                'cancel_url' => route('paypal.status'), // Redirect URL if canceled
            ],
        ];

        // Set the express checkout payment
        $response = $provider->createOrder($data);
        // dd($response);
        if (isset($response['links']) && !empty($response['links'])) {
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    // Redirect to PayPal approval page
                    // dd($link['href']);

                    return response()->json(['status' => "", 'link' => $link['href']], 200);
                }
            }
        }

        return redirect()->route('paypal.status')->with('error', 'Error occurred while creating PayPal payment');
    }


    // Payment Status
    public function paymentStatus(Request $request)
    {
        // dd("safds");
        $token = $request->get('token'); // PayPal order token
        $payerId = $request->get('PayerID'); // Payer ID from PayPal (if applicable)

        $provider = new PayPalClient();
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        dd($token);
        try {
            // Capture the payment order
            $response = $provider->capturePaymentOrder($token);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                dd("THANK YOU");
                // return redirect()->route('home')->with('success', 'Payment successful');
            }

            return redirect()->route('home')->with('error', 'Payment could not be completed');
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }

    public function payWithCard(Request $request)
    {
        $provider = new PayPalClient();

        // Validate incoming request
        $request->validate([
            'card_number' => 'required|digits:16',
            'expiry_month' => 'required|digits:2',
            'expiry_year' => 'required|digits:4',
            'cvv' => 'required|digits:3',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $provider->setApiCredentials(config('paypal'));
            $accessToken = $provider->getAccessToken();
            $requestId = Str::uuid()->toString(); // Generate a unique UUID
            $provider->addRequestHeader('PayPal-Request-Id', $requestId);


            // Payment Data
            $data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => $request->amount,
                        ],
                    ],
                ],
                'payment_source' => [
                    'card' => [
                        'number' => $request->card_number,
                        'expiry' => $request->expiry_month . '/' . $request->expiry_year,
                        'security_code' => $request->cvv,
                    ],
                ],
            ];

            // Create Payment Order
            $response = $provider->createOrder($data);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                return response()->json(['message' => 'Payment successful', 'data' => $response], 200);
            }

            return response()->json(['message' => 'Payment could not be completed', 'data' => $response], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Payment failed', 'error' => $e->getMessage()], 500);
        }
    }
}

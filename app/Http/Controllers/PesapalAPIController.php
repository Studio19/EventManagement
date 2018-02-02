<?php

namespace App\Http\Controllers;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input as Input;
use Pesapal;
use App\Http\Requests;
use Carbon\Carbon;


class PesapalAPIController extends Controller
{


	/**
     * Is the checkout in an embedded Iframe?
     *
     * @var bool
     */
    protected $is_embedded;


        /**
     * EventCheckoutController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        /*
         * See if the checkout is being called from an embedded iframe.
         */
        $this->is_embedded = $request->get('is_embedded') == '1';
    }

    //
     function handleCallback()
    {
        $merchant_reference = Input::get('pesapal_merchant_reference');
        $tracking_id = Input::get('pesapal_transaction_tracking_id');
        $route = config('pesapal.callback_route');
        return redirect()->route($route,
            array('tracking_id' => $tracking_id, 'merchant_reference' => $merchant_reference));
    }

    function handleIPN(Request $request, $event_id)
    {
        if (/*Input::has('pesapal_notification_type') && */Input::has('pesapal_merchant_reference') && Input::has('pesapal_transaction_tracking_id')) {
            $notification_type = Input::get('pesapal_notification_type');
            $merchant_reference = Input::get('pesapal_merchant_reference');
            $tracking_id = Input::get('pesapal_transaction_tracking_id');
            Pesapal::redirectToIPN($notification_type, $merchant_reference, $tracking_id);
        } else {
            throw new PesapalException("incorrect parameters in request");
        }
        
        //return view('Public.ViewEvent.Partials.EventCreateOrderSection2');
        //dd($merchant_reference,$tracking_id);

        $order_session = session()->get('ticket_order_' . $event_id);

       

        $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);

        $data = $order_session + [
                'event'           => Event::findorFail($order_session['event_id']),
                'secondsToExpire' => $secondsToExpire,
                'is_embedded'     => $this->is_embedded,
            ];

        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventPageCheckout', $data);
        }

        return view('Public.ViewEvent.EventPageCheckout2', $data);
    
    }
}

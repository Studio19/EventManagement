<?php

namespace App\Http\Controllers;//3,683,690//event/2/pesament/skip

//use App\Events\DonationCompletedEvent;
use App\Events\OrderCompletedEvent;
use App\Models\Affiliate;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventStats;
use App\Models\Order;
use Illuminate\Support\Facades\URL;
use App\Coupon;
use Session;
use App\Acccommodation;
//use App\Models\Donation;
use App\Models\OrderItem;
use App\Models\QuestionAnswer;
use App\Models\ReservedTickets;
use App\Models\Ticket;
use Carbon\Carbon;
use Cookie;
use DB;
use Illuminate\Http\Request;
use Log;
use Omnipay;
use PDF;
use PhpSpec\Exception\Exception;
use Validator;
use Utils;

class EventCheckoutController extends Controller
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

    /**
     * Validate a ticket request. If successful reserve the tickets and redirect to checkout
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function postValidateTickets(Request $request, $event_id)
    {
        session()->set('transaction_'.$event_id,'tickets');
        /*
         * Order expires after X min
         */
        $subscription=null;
        $donation = 0; //DonaldFeb9
        $order_has_validdiscount=[]; //contains ['ticketid'=>'couponcode'] for valid coupons
    //    $sideeventnotes = []; //DonaldMar13 commented by DonaldMar14
        $order_expires_time = Carbon::now()->addMinutes(config('attendize.checkout_timeout_after'));

        $event = Event::findOrFail($event_id);
        if($request->has('order_ref')){
            if (!$request->has('tickets')) {
            return $this->handlereordering($request, $event_id);
            }
            $ticket_ids=$request->get('tickets'); 
            $ticketselected=false; $walker=0;          
            while ($walker < count($ticket_ids) && !$ticketselected) {
                $current_ticket_quantity = (int)$request->get('ticket_' . $ticket_ids[$walker]);
                if ($current_ticket_quantity >= 1) {
                    $ticketselected=true;
                }
                ++$walker;
            }
            if(!$ticketselected){
                return $this->handlereordering($request, $event_id);
            }
            $edit_order = Order::where('order_reference', '=', $request->get('order_ref'))->first();
            if(!$edit_order){
                    $errordata = [
                        'event' => $event,
                        'callbackurl' => null,
                        'messages' => 'Sorry, we couldn\'t find any order with that reference. Please make sure that you enter it correctly',
                        'request_details' => null,
                        'parameters' => ['event_id' => $event_id]
                    ];
                    return view('Public.ViewEvent.EventPageErrors', $errordata);
            }
            $past_items = $edit_order->orderItems;
            $past_tickets=[];
            $past_donation=0;
            $past_order_amount=$edit_order->amount;
            /*foreach($past_items as $past_item){
                if($past_item['title']=='Donation'){
                    $past_donation=$past_item['unit_price'];
                }else{
                    $past_tickets[] = [
                        'ticket'                => Ticket::find($ticket_id),
                        'ticket_title'          => $past_item->title,
                        'qty'                   => $past_item->quantity,
                        'price'                 => ($past_item->quantity * $past_item->unit_price),
                        'booking_fee'           => ($past_item->quantity * $past_item->unit_booking_fee),
                        'organiser_booking_fee' => ($past_item->quantity * $edit_order->organiser_booking_fee),
                        'full_price'            => $past_item->unit_price + $edit_order->organiser_booking_fee + $edit_order->booking_fee,
                    ];
                }
            }*/
                            $art_tickets = [];
                            foreach ($edit_order->orderItems as $orderItem) {
                                if($orderItem->title=='Donation'){
                                        /*$art_tickets['donation'] = [
                                            'quantity' => 1,
                                            'total' => $orderItem->unit_price,
                                            'title' => $orderItem->title,
                                            'price' => $orderItem->unit_price,
                                            'booking_fee' => $orderItem->unit_booking_fee
                                        ];*/
                                        $past_donation = $orderItem->unit_price;
                                }
                            }
                            foreach($edit_order->attendees as $order_attendee) {
                                if(!$order_attendee->is_cancelled){
                                    if(array_key_exists($order_attendee->ticket->id, $art_tickets)){
                                        $art_tickets[$order_attendee->ticket->id]['qty']                   += 1;
                                        $art_tickets[$order_attendee->ticket->id]['price']                 +=  $order_attendee->ticket->price;
                                        $art_tickets[$order_attendee->ticket->id]['booking_fee']           += $order_attendee->ticket->booking_fee;
                                        $art_tickets[$order_attendee->ticket->id]['organiser_booking_fee'] += $edit_order->organiser_booking_fee;
                                    }else{
                                        $art_tickets[$order_attendee->ticket->id] = [
                                            'ticket' => Ticket::find($order_attendee->ticket->id),
                                            'qty' => 1,
                                            'ticket_title' => $order_attendee->ticket->title,
                                            'price'                 => $order_attendee->ticket->price,
                                            'booking_fee'           => $order_attendee->ticket->booking_fee,
                                            'organiser_booking_fee' => $edit_order->organiser_booking_fee,
                                            'full_price'            => $order_attendee->ticket->price + $edit_order->organiser_booking_fee + $edit_order->booking_fee,
                                            'dates'                 => str_replace(['to','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], ['<==>','01','02','03','04','05','06','07','08','09','10','11','12'], $order_attendee->period)
                                        ];
                                    }
                                }
                            }
            $past_order_id = $edit_order->id;
            $first_name=$edit_order->first_name;
            $last_name=$edit_order->last_name;
            $email=$edit_order->email;
            goto handletickets;
        }

        if (!$request->has('tickets')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tickets selected',
            ]);
        }

        $first_name = $request->get('first_name');
        $last_name = $request->get('last_name');
        $email = $request->get('email');

        handletickets:
        if($request->has('subscription')){
          $subscription='Subscribed';
        }
        $ticket_ids = $request->get('tickets');


        /*
         * Remove any tickets the user has reserved
         */
        ReservedTickets::where('session_id', '=', session()->getId())->delete();

        /*
         * Go though the selected tickets and check if they're available
         * , tot up the price and reserve them to prevent over selling.
         */

        $validation_rules = [];
        $validation_messages = [];
        $tickets = [];
        $coupon_flag = [];
        $order_total = 0;
        $total_ticket_quantity = 0;
        $booking_fee = 0;
        $organiser_booking_fee = 0;
        $quantity_available_validation_rules = [];
        $donation_ticket_price = 0;
        $amount_array = [];
        $amount_title = [];
        $discount_array = [];
        $discount_ticket_title = [];

        foreach ($ticket_ids as $ticket_id) {
            $current_ticket_quantity = (int)$request->get('ticket_' . $ticket_id);

            if ($current_ticket_quantity < 1) {
                continue;
            }

            $total_ticket_quantity = $total_ticket_quantity + $current_ticket_quantity;

            $ticket = Ticket::find($ticket_id);

           //Get the price of the most expensive ticket, to be used for donation
            if($ticket->price > $donation_ticket_price){
             $donation_ticket_price = $ticket->price;
            }

            $ticket_quantity_remaining = $ticket->quantity_remaining;


            $max_per_person = min($ticket_quantity_remaining, $ticket->max_per_person);

            $quantity_available_validation_rules['ticket_' . $ticket_id] = [
                'numeric',
                'min:' . $ticket->min_per_person,
                'max:' . $max_per_person
            ];

            $quantity_available_validation_messages = [
                'ticket_' . $ticket_id . '.max' => 'The maximum number of tickets you can register is ' . $ticket_quantity_remaining,
                'ticket_' . $ticket_id . '.min' => 'You must select at least ' . $ticket->min_per_person . ' tickets.',
            ];

            $validator = Validator::make(['ticket_' . $ticket_id => (int)$request->get('ticket_' . $ticket_id)],
                $quantity_available_validation_rules, $quantity_available_validation_messages);

            if ($validator->fails()) {
                $errordata = [
                    'event' => $event,
                    'callbackurl' => null,
                    'messages' => $validator->messages()->toArray(),
                    'request_details' => null,
                    'parameters' => ['event_id' => $event_id]
                ];
                return view('Public.ViewEvent.EventPageErrors', $errordata);
                /*return response()->json([
                    'status'   => 'error',
                    'messages' => $validator->messages()->toArray(),
                ]);*/
            }

            /*
            * Coupon code array validation (Frank) edited April
            *
            */
           $coupon_code = $request->get('coupon_' . $ticket_id);

           if ($coupon_code!='') {
                $coupon_single = Coupon::where('coupon_code','=', $coupon_code)->first();
                if(!$coupon_single){
                    //$coupon_state = 'Invalid';
                            $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
                            $amount_array[] =null;
                            $amount_title[] =null;
                            $discount_array[] = null;
                            $discount_ticket_title[] = null;
            return response()->json([
                'status'  => 'error',
                'message' => 'The coupon used couldn\'t be matched to the ticket. Please make sure that the two correspond before continuing.',
            ]);
                //    session()->flash('message', 'The coupon used couldn\'t be matched to the ticket. Please make sure the two correspond before continuing.');
                }
                else {
                    if ($coupon_single->state=='Valid') {
                        if ($coupon_single->ticket_id==$ticket_id && $coupon_single->discount!='') {
                            $coupon_flag[] = $coupon_code;
                            $order_has_validdiscount[$ticket->id] = $coupon_code;
                            $order_total = $order_total + ($current_ticket_quantity * $ticket->price)  - ($ticket->price*($coupon_single->discount/100));

                            $discount_array[] = $coupon_single->discount;
                            $discount_ticket_title[] = $coupon_single->ticket;
                            $amount_array[] ='';
                            $amount_title[] ='';
                        }
                        if ($coupon_single->ticket_id==$ticket_id && $coupon_single->exact_amount!='') {
                            $coupon_flag[] = $coupon_code;
                            $order_has_validdiscount[$ticket->id] = $coupon_code;
                            $order_total = $order_total + $coupon_single->exact_amount;
                            $amount_array[] =$coupon_single->exact_amount;
                            $amount_title[] =$coupon_single->ticket;
                            $discount_array[] = '';
                            $discount_ticket_title[] = '';
                        }
                        else if ($coupon_single->ticket_id!=$ticket_id) {
                            $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
                            $amount_array[] =null;
                            $amount_title[] =null;
                            $discount_array[] = null;
                            $discount_ticket_title[] = null;
            return response()->json([
                'status'  => 'error',
                'message' => 'The coupon used couldn\'t be matched to the ticket. Please make sure that the two correspond before continuing.',
            ]);
                        }
                    }
                     else{
                    //$coupon_state = 'Used';
                            $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
                            $amount_array[] =null;
                            $amount_title[] =null;
                            $discount_array[] = null;
                            $discount_ticket_title[] = null;
            return response()->json([
                'status'  => 'error',
                'message' => 'The coupon seems to be already used. You have to clear the field to continue.',
            ]);
                    }
                }
            }
            else{ 
                $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
                            $amount_array[] ='';
                            $amount_title[] ='';
                            $discount_array[] = '';
                            $discount_ticket_title[] = '';

                }
            /*
             *
             # End of Coupon Validation...
             *
             */

            //$order_total = $order_total + ($current_ticket_quantity * $ticket->price);
            $booking_fee = $booking_fee + ($current_ticket_quantity * $ticket->booking_fee);
            $organiser_booking_fee = $organiser_booking_fee + ($current_ticket_quantity * $ticket->organiser_booking_fee);

            $tickets[] = [
                'ticket'                => $ticket,
                'qty'                   => $current_ticket_quantity,
                'price'                 => ($current_ticket_quantity * $ticket->price),
                'booking_fee'           => ($current_ticket_quantity * $ticket->booking_fee),
                'organiser_booking_fee' => ($current_ticket_quantity * $ticket->organiser_booking_fee),
                'full_price'            => $ticket->price + $ticket->total_booking_fee,
            ];

            /*
             * Reserve the tickets for X amount of minutes
             */
            $reservedTickets = new ReservedTickets();
            $reservedTickets->ticket_id = $ticket_id;
            $reservedTickets->event_id = $event_id;
            $reservedTickets->quantity_reserved = $current_ticket_quantity;
            $reservedTickets->expires = $order_expires_time;
            $reservedTickets->session_id = session()->getId();
            $reservedTickets->save();

            for ($i = 0; $i < $current_ticket_quantity; $i++) {
                /*
                 * Create our validation rules here
                 */
                $validation_rules['ticket_holder_first_name.' . $i . '.' . $ticket_id] = ['required'];
                $validation_rules['ticket_holder_last_name.' . $i . '.' . $ticket_id] = ['required'];
                $validation_rules['ticket_holder_email.' . $i . '.' . $ticket_id] = ['required', 'email'];

                $validation_messages['ticket_holder_first_name.' . $i . '.' . $ticket_id . '.required'] = 'Ticket holder ' . ($i + 1) . '\'s first name is required';
                $validation_messages['ticket_holder_last_name.' . $i . '.' . $ticket_id . '.required'] = 'Ticket holder ' . ($i + 1) . '\'s last name is required';
                $validation_messages['ticket_holder_email.' . $i . '.' . $ticket_id . '.required'] = 'Ticket holder ' . ($i + 1) . '\'s email is required';
                $validation_messages['ticket_holder_email.' . $i . '.' . $ticket_id . '.email'] = 'Ticket holder ' . ($i + 1) . '\'s email appears to be invalid';

                /*
                 * Validation rules for custom questions
                 */
                foreach ($ticket->questions as $question) {

                    if ($question->is_required && $question->is_enabled) {
                        $validation_rules['ticket_holder_questions.' . $ticket_id . '.' . $i . '.' . $question->id] = ['required'];
                        $validation_messages['ticket_holder_questions.' . $ticket_id . '.' . $i . '.' . $question->id . '.required'] = "This question is required";
                    }

                }

            }

        }

        //Check if the checkbox is clicked and determine the corresponding donation amount
        if ($request->has('donation')) {
         //Calculate the Default Donation being 5% of highest ticket price
           $donation = $request->get('donation');
        }
        elseif($request->has('defaultdonation')){
            $donation = $donation_ticket_price * 0.05;
        }

    /* Redundant???    if (empty($tickets)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tickets selected.',
            ]);
        }
    */

        /*
         * The 'ticket_order_{event_id}' session stores everything we need to complete the transaction.
         */
        session()->set('ticket_order_' . $event->id, [
            'validation_rules'        => $validation_rules,
            'first_name'              => $first_name,
            'last_name'               => $last_name,
            'email'                   => $email,
            'coupon_flag'             => $coupon_flag,
            'discount'                => $discount_array,
            'discount_ticket_title'   => $discount_ticket_title,
            'exact_amount'            => $amount_array,
            'amount_ticket_title'     => $amount_title,
            'validation_messages'     => $validation_messages,
            'event_id'                => $event->id,
            'tickets'                 => $tickets,
            'total_ticket_quantity'   => $total_ticket_quantity,
            'order_started'           => time(),
            'expires'                 => $order_expires_time,
            'reserved_tickets_id'     => $reservedTickets->id,
            'order_total'             => $order_total,
            'donation'                => $donation, //DonaldFeb9
            'order_has_validdiscount' => $order_has_validdiscount,
            'order_subscription'      => $subscription,
        //    'sideeventnotes'          => $sideeventnotes, //DonaldMar13 commented by DonaldMar14
            'booking_fee'             => $booking_fee,
            'organiser_booking_fee'   => $organiser_booking_fee,
            'total_booking_fee'       => $booking_fee + $organiser_booking_fee,
            'order_requires_payment'  => (ceil($order_total) == 0) ? false : true,
            'account_id'              => $event->account->id,
            'affiliate_referral'      => Cookie::get('affiliate_' . $event_id),
            'account_payment_gateway' => count($event->account->active_payment_gateway) ? $event->account->active_payment_gateway : false,
            'payment_gateway'         => count($event->account->active_payment_gateway) ? $event->account->active_payment_gateway->payment_gateway : false,
        ]);

        if(isset($past_order_id)){
            session()->put('ticket_order_' . $event_id . '.past_order_id',$past_order_id);
        //    session()->put('ticket_order_' . $event_id . '.past_tickets',$past_tickets);
            session()->put('ticket_order_' . $event_id . '.past_tickets',$art_tickets);
            session()->put('ticket_order_' . $event_id . '.past_donation', $past_donation);
            session()->put('ticket_order_' . $event_id . '.past_order_amount', $past_order_amount);
        }
        /*
         * If we're this far assume everything is OK and redirect them
         * to the the checkout page.
         */
        if ($request->ajax()) {
            return response()->json([
                'status'      => 'success',
                  'redirectUrl' => route('handleTransactions', [
                        'event_id'    => $event_id,
                    ])
            ]);
        }

        /*
         * Maybe display something prettier than this?
         */
        //exit('Please enable Javascript in your browser.');

        return $this->javascriptError($event_id);
    }

    public function handleReOrdering($request, $event_id){
        $event=Event::findOrFail($event_id);
        $order_reference = $request['order_ref'];
        $edit_order = Order::where('order_reference', '=', $order_reference)->first();
        if(!$edit_order){
                $errordata = [
                    'event' => $event,
                    'callbackurl' => null,
                    'messages' => 'Sorry, we couldn\'t find any order with that reference. Please make sure that you enter it correctly',
                    'request_details' => null,
                    'parameters' => ['event_id' => $event_id]
                ];
                return view('Public.ViewEvent.EventPageErrors', $errordata);
            //exit ('no order with provided reference exists');
        }
        $first_name = $edit_order->first_name;
        $last_name = $edit_order->last_name;
        $email = $edit_order->email;
        $order_expires_time = Carbon::now()->addMinutes(config('attendize.checkout_timeout_after'));
    /*    if([$first_name,$last_name,$email]!==[$edit_order->first_name,$edit_order->last_name,$edit_order->email]){
            $errordata = [
                'event' => $event,
                'callbackurl' => null,
                'messages' => 'There was a mismatch in the details. Please provide correct details as those in your past order.',
                'request_details' => null,
                'parameters' => ['event_id' => $event_id]
            ];
            return view('Public.ViewEvent.EventPageErrors', $errordata);
            //exit ('there was a mismatch in the details');
        }
    */

        $past_items = $edit_order->orderItems;
        $donation_ticket_price=0;
        $past_tickets=[];
        $past_donation=0;
        $past_order_amount=$edit_order->amount;
        //dd($edit_order->amount);
                            $art_tickets = [];
                            foreach ($edit_order->orderItems as $orderItem) {
                                if($orderItem->title=='Donation'){
                                        /*$art_tickets['donation'] = [
                                            'quantity' => 1,
                                            'total' => $orderItem->unit_price,
                                            'title' => $orderItem->title,
                                            'price' => $orderItem->unit_price,
                                            'booking_fee' => $orderItem->unit_booking_fee
                                        ];*/
                                        $past_donation = $orderItem->unit_price;
                                }
                            }
                            foreach($edit_order->attendees as $order_attendee) {
                                if(!$order_attendee->is_cancelled){
                                    if(array_key_exists($order_attendee->ticket->id, $art_tickets)){
                                        $art_tickets[$order_attendee->ticket->id]['qty']                   += 1;
                                        $art_tickets[$order_attendee->ticket->id]['price']                 +=  $order_attendee->ticket->price;
                                        $art_tickets[$order_attendee->ticket->id]['booking_fee']           += $order_attendee->ticket->booking_fee;
                                        $art_tickets[$order_attendee->ticket->id]['organiser_booking_fee'] += $edit_order->organiser_booking_fee;
                                    }else{
                                        $art_tickets[$order_attendee->ticket->id] = [
                                            'ticket' => Ticket::find($order_attendee->ticket->id),
                                            'qty' => 1,
                                            'ticket_title' => $order_attendee->ticket->title,
                                            'price'                 => $order_attendee->ticket->price,
                                            'booking_fee'           => $order_attendee->ticket->booking_fee,
                                            'organiser_booking_fee' => $edit_order->organiser_booking_fee,
                                            'full_price'            => $order_attendee->ticket->price + $edit_order->organiser_booking_fee + $edit_order->booking_fee,
                                            'dates'                 => str_replace(['to','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], ['<==>','01','02','03','04','05','06','07','08','09','10','11','12'], $order_attendee->period)
                                        ];
                                    }
                                }
                            }

    /*
        foreach($past_items as $past_item){
            if($past_item['title']=='Donation'){
                $past_donation=$past_item['unit_price'];
            }else{
                if($past_item['unit_price']>$donation_ticket_price){
                $donation_ticket_price = $past_item['unit_price'];
                }
                $past_tickets[] = [
                    'ticket_title'          => $past_item->title,
                    'qty'                   => $past_item->quantity,
                    'price'                 => ($past_item->quantity * $past_item->unit_price),
                    'booking_fee'           => ($past_item->quantity * $past_item->unit_booking_fee),
                    'organiser_booking_fee' => ($past_item->quantity * $edit_order->organiser_booking_fee),
                    'full_price'            => $past_item->unit_price + $edit_order->organiser_booking_fee + $edit_order->booking_fee,
                ];
            }
        }
    */    

        //Check if the checkbox is clicked and determine the corresponding donation amount
        if ($request->has('donation')) {
           $donation = $request->get('donation');
        }elseif($request->has('defaultdonation')){
            $donation = $donation_ticket_price * 0.05;
        }else{
            $donation = 0;
        }
        session()->set('ticket_order_' . $event->id, [
            'validation_rules'        => [],
            'first_name'              => $first_name,
            'last_name'               => $last_name,
            'email'                   => $email,
            'coupon_flag'             => [],
            'discount'                => [],
            'discount_ticket_title'   => [],
            'exact_amount'            => [],
            'amount_ticket_title'     => [],
            'validation_messages'     => [],
            'event_id'                => $event->id,
            'tickets'                 => [],
            'total_ticket_quantity'   => 0,
            'order_started'           => time(),
            'expires'                 => $order_expires_time,
            'reserved_tickets_id'     => [],
            'order_total'             => 0,
            'donation'                => $donation,
            'order_has_validdiscount' => [],
            'order_subscription'      => 0,
            'booking_fee'             => 0,
            'organiser_booking_fee'   => 0,
            'total_booking_fee'       => 0,
            'order_requires_payment'  => true,
            'account_id'              => $event->account->id,
            'affiliate_referral'      => Cookie::get('affiliate_' . $event_id),
            'account_payment_gateway' => count($event->account->active_payment_gateway) ? $event->account->active_payment_gateway : false,
            'payment_gateway'         => count($event->account->active_payment_gateway) ? $event->account->active_payment_gateway->payment_gateway : false,
            'past_order_id'              => $edit_order->id,
        //    'past_tickets'            => $past_tickets,
            'past_tickets'            => $art_tickets,
            'past_donation'           => $past_donation,
            'past_order_amount'       => $past_order_amount,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'status'      => 'success',
                  'redirectUrl' => route('handleTransactions', [
                        'event_id'    => $event_id,
                    ])
            ]);
        }
        return redirect()->route('handleTransactions', ['event_id' => $event_id]);
    }

    public function javascriptError($event_id){
        $errordata = [
            'event' => Event::findOrFail($event_id),
            'callbackurl' => 'createorder',
            'messages' => "Javascript is not enabled in your browser. Please enable it first before you continue",
            'parameters' => ['event_id' => $event_id]
        ];
        return view('Public.ViewEvent.EventPageErrors', $errordata);
    }

    public function sessionExpiredError($event_id,$route){
        $errordata = [
            'event' => Event::findOrFail($event_id),
            'callbackurl' => 'createorder',
            'messages' => "Your session has expired. Please restart the process.",
            'parameters' => ['event_id' => $event_id],
            'route'   => $route,
            'routeparameters'   => ['event_id' => $event_id],
            'routedisplay' => 'Please click here to restart the process'
        ];
        return view('Public.ViewEvent.EventPageErrors', $errordata);
    }

    //added by DonaldApril25
    public function organiserSkipPayment($event_id)
    {
        $order_session = session()->get('ticket_order_' . $event_id);
        $event=Event::findOrFail($event_id);
        if(Utils::userOwns($event) || $order_session['order_total'] + $order_session['donation'] == 0 && count($order_session['order_has_validdiscount'])>0){
            session()->set('transaction_'.$event_id,'complete');
            $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);
            $data = $order_session + [
                    'event'           => Event::findorFail($order_session['event_id']),
                    'secondsToExpire' => $secondsToExpire,
                    'is_embedded'     => $this->is_embedded,
                    'previousurl' => URL::previous(),
                ];
            if ($this->is_embedded) {
                return view('Public.ViewEvent.Embedded.EventPageCheckoutSuccess', $data);
            }
                return view('Public.ViewEvent.EventPageCheckoutSuccess', $data);
        }else{
            return redirect()->back();
        }
    }

    /**
     * Added by DonaldMar16 to show order side events page
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showOrderSideEvents($event_id)
    {
        $order_session = session()->get('ticket_order_' . $event_id);

        session()->set('transaction_'.$event_id,'sideevents');

        if (!$order_session || $order_session['expires'] < Carbon::now()) {
            $route_name = $this->is_embedded ? 'showEmbeddedEventPage' : 'showEventPage';
            session()->forget('ticket_order_' . $event_id);
            return redirect()->route($route_name, ['event_id' => $event_id]);
        }

        $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);
        $sideeventsar   = Ticket::where(['type'=>'SIDEEVENT','event_id'=>$event_id])->get();
        $event = Event::findOrFail($event_id);

        if(!$sideeventsar->count()){
            $data = $order_session + [
                    'event'           => $event,
                    'secondsToExpire' => $secondsToExpire,
                    'coupon_flag'           => $order_session['coupon_flag'],
                    'discount'              => $order_session['discount'],
                    'discount_ticket_title' => $order_session['discount_ticket_title'],
                    'exact_amount'          => $order_session['exact_amount'],
                    'amount_ticket_title'   => $order_session['amount_ticket_title'],
                    'is_embedded'     => $this->is_embedded,
                ];
        /*
         * If there're no side events,
         */
        return redirect()->route('showEventCheckout', ['event_id' => $event_id]);

        /*
         * Maybe display something prettier than this?
         */
        //exit('Please enable Javascript in your browser.');
        return $this->javascriptError($event_id);
        }

        $data = $order_session + [
                'event'           => $event,
                'sideeventsar'   => $sideeventsar,
                'secondsToExpire' => $secondsToExpire,
                'coupon_flag'           => $order_session['coupon_flag'],
                'discount'              => $order_session['discount'],
                'discount_ticket_title' => $order_session['discount_ticket_title'],
                'exact_amount'          => $order_session['exact_amount'],
                'amount_ticket_title'   => $order_session['amount_ticket_title'],
                'is_embedded'     => $this->is_embedded,
            ];

            //dd($data);

        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventSideEvent', $data);
        }

        return view('Public.ViewEvent.EventSideEvent', $data);
    }


    /**
     * Added by DonaldMar16 to show order side events page
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showOrderAccommodation($event_id)
    {
        $order_session = session()->get('ticket_order_' . $event_id);

        session()->set('transaction_'.$event_id,'accommodation');

        if (!$order_session || $order_session['expires'] < Carbon::now()) {
            $route_name = $this->is_embedded ? 'showEmbeddedEventPage' : 'showEventPage';
            session()->forget('ticket_order_' . $event_id);
            return redirect()->route($route_name, ['event_id' => $event_id]);
        }

        $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);

        //Retrive all the links from the tickets
        $ticket_links= [];

        $i = 0;

        $purtickets = $order_session['tickets'];
        if(isset($order_session['past_tickets'])){
            $purtickets = array_merge($purtickets, $order_session['past_tickets']);
        }

        foreach($purtickets as $ticket)
        {
         if($ticket['ticket']->ticket_links != ""){
         $ticket_links[$i] = (int)$ticket['ticket']->ticket_links;
         ++$i;
         }
        }

        //If no links is initialized, then retrieve all tickets with extras
        if(count($ticket_links) == 0){
        $accomodations = Ticket::where('type','Extra')->get();
        }
       else {
        $accomodations = Ticket::whereIn('id',$ticket_links)->orWhere(['status'=>'','type'=>'Extra'])->orWhere(['status'=>null, 'type'=>'Extra'])->get();
       }

        $data = $order_session + [
                'event'           => Event::findorFail($order_session['event_id']),
                'secondsToExpire' => $secondsToExpire,
                'coupon_flag'           => $order_session['coupon_flag'],
                'discount'              => $order_session['discount'],
                'newSubTotal'              => '',
                'first_name'              => $order_session['first_name'],
                'last_name'              => $order_session['last_name'],
                'email'              => $order_session['email'],
                'accomodations'              => $accomodations,
                'discount_ticket_title' => $order_session['discount_ticket_title'],
                'exact_amount'          => $order_session['exact_amount'],
                'amount_ticket_title'   => $order_session['amount_ticket_title'],
                'is_embedded'     => $this->is_embedded,
            ];

        // Check if accommodation exists
        if (count($accomodations)>0){
          return view('Public.ViewEvent.Accomodation', $data);
        }
        else { //If not proceed to checkout
         return redirect()->route('showEventCheckout', ['event_id' => $event_id]);
        }

    }


    public function updateBooking(Request $request)
    {

        $event_id = $request->get('event_id');
        $order_session = session()->get('ticket_order_' . $event_id);

        //$value = $request->session()->pull('key', $order_session['order_total']);
        //dd(count($request->get('mydates')));

        if (!$order_session || $order_session['expires'] < Carbon::now()) {
            $route_name = $this->is_embedded ? 'showEmbeddedEventPage' : 'showEventPage';
            session()->forget('ticket_order_' . $event_id);
            return redirect()->route($route_name, ['event_id' => $order_session]);
        }

        $name = $request->get('first_name'). $request->get('last_name');

        $order_session['order_total'] = $request->get('old_total') + $request->get('days') * $request->get('price');
        //$newTotal += $request->get('old_total');

       $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);
        $accomodations = Ticket::where('type','Extra')->get();

       //Session::put('order_total', $newTotal);

       //dd($newTotal);

        Acccommodation::create([
                'full_name' => $name,
                'email' => $request->get('email'),
                'hotel_status' => $request->get('status'),
                'title' => $request->get('title'),
                'amount' =>  $order_session['order_total'],
                'days' =>  count($request->get('mydates')),
                'date' =>  $request->get('mydates'),
              ]);



          $data = $order_session + [
                'event'           => Event::findorFail($order_session['event_id']),
                'secondsToExpire' => $secondsToExpire,
                'coupon_flag'           => $order_session['coupon_flag'],
                'discount'              => $order_session['discount'],
                'first_name'              => $order_session['first_name'],
                'order_total'              => $order_session['order_total'],
                'last_name'              => $order_session['last_name'],
                'email'              => $order_session['email'],
                'accomodations'              => $accomodations,
                'discount_ticket_title' => $order_session['discount_ticket_title'],
                'exact_amount'          => $order_session['exact_amount'],
                'amount_ticket_title'   => $order_session['amount_ticket_title'],
                'is_embedded'     => $this->is_embedded,
            ];

            //dd($data);

        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventPageCheckout', $data);
        }

        return view('Public.ViewEvent.Accomodation', $data);

        //dd($order_session['order_total']);
    }

    /**
     * Added by DonaldMar16 to show post order side events page
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function postOrderSideEvents(Request $request, $event_id)
    {

        $event = Event::findOrFail($event_id);

        $order_session['order_total'] = $request->get('old_total') + $request->get('days') * $request->get('price');

        //Get the values from the fields
        $fullname = $request->get('first_name'). $request->get('last_name');
        $email = $request->get('email');
        $hotel_status = $request->get('hotel_status');
        $title = $request->get('title');
        $amount =  $order_session['order_total'];
        $price = $request->get('price');
        $ticket_id = $request->get('ticket_id');
        $accommodation_dates =  $request->get('mydates');

        //Retrieve information from the form
          $ticket_id = $request->get('ticket_id');
          $ticket_quantity = $request->get('ticket_'.$ticket_id);
          $ticket_price = $request->get('price');
          $ticket_dates = $request->get('mydates');


        //Retrieve the old Total
        $old_total = $request->get('old_total');

        //Make calculations of the new total
        $new_total = $old_total + ($ticket_quantity*$price);

        //dd("Old total was ". $old_total . " and the New Total is ". $new_total);

        //TODO Clean up this section
        //$secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);
        //$ticket = Ticket::where('type','Extra')->get();
        // $ticket = Ticket::find($ticket_id);
       /*
         * Remove any tickets the user has reserved
         */
    //    ReservedTickets::where('session_id', '=', session()->getId())->delete();

        /*
         * Go though the selected tickets and check if they're available
         * , tot up the price and reserve them to prevent over selling.
         */

        $availables              =    session()->get('ticket_order_' . $event_id);
        $tickets                 =    $availables['tickets'];
        $order_total             =    $availables['order_total'];
        $total_ticket_quantity   =    $availables['total_ticket_quantity'];
        $booking_fee             =    $availables['booking_fee'];
        $organiser_booking_fee   =    $availables['organiser_booking_fee'];
        $discount                =    $availables['discount'];
        $discount_ticket_title   =    $availables['discount_ticket_title'];
        $exact_amount            =    $availables['exact_amount'];
        $amount_ticket_title     =    $availables['amount_ticket_title'];
        $quantity_available_validation_rules = [];

        //dd("Order total from session is " .$order_total . "Order total from form is " . $old_total);
        //dd($tickets);

        //Checks if there are any tickets selected
        //TODO make sure the check works
       // if(!empty($ticket_ids)){
           // foreach ($ticket_ids as $ticket_id) {
               //Gets the Ticket Quantity
                //$current_ticket_quantity = (int)$request->get('ticket_' . $ticket_id);
                $current_ticket_quantity = $ticket_quantity;

                /*
                if ($current_ticket_quantity < 1) {
                    continue;
                }
                */

               // dd($availables);
                //Updates the ticket quantity
                $total_ticket_quantity = $total_ticket_quantity + $current_ticket_quantity;

                //Retrieves ticket information from the database
                //dd($ticket_id);
                $ticket = Ticket::find($ticket_id);

                //
                $ticket_quantity_remaining = $ticket->quantity_remaining;

                //
                $max_per_person = min($ticket_quantity_remaining, $ticket->max_per_person);

                //
                $quantity_available_validation_rules['ticket_' . $ticket_id] = [
                    'numeric',
                    'min:' . $ticket->min_per_person,
                    'max:' . $max_per_person
                ];
               // dd($order_total);

                //
                /*
                $quantity_available_validation_messages = [
                    'ticket_' . $ticket_id . '.max' => 'The maximum number of tickets you can register is ' . $ticket_quantity_remaining,
                    'ticket_' . $ticket_id . '.min' => 'You must select at least ' . $ticket->min_per_person . ' tickets.',
                ];
                */
                /*

                $validator = Validator::make(['ticket_' . $ticket_id => (int)$request->get('ticket_' . $ticket_id)],
                    $quantity_available_validation_rules, $quantity_available_validation_messages);

                if ($validator->fails()) {
                    return response()->json([
                        'status'   => 'error',
                        'messages' => $validator->messages()->toArray(),
                    ]);
                }
                */

                $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
                //dd($order_total);
                $booking_fee = $booking_fee + ($current_ticket_quantity * $ticket->booking_fee);
                $organiser_booking_fee = $organiser_booking_fee + ($current_ticket_quantity * $ticket->organiser_booking_fee);

                //Appends Ticket information to the Ticket Variable that will be stored in the session
                $tickets[count($tickets)] = [
                    'ticket'                => $ticket,
                    'qty'                   => $current_ticket_quantity,
                    'price'                 => ($current_ticket_quantity * $ticket->price),
                    'booking_fee'           => ($current_ticket_quantity * $ticket->booking_fee),
                    'organiser_booking_fee' => ($current_ticket_quantity * $ticket->organiser_booking_fee),
                    'full_price'            => $ticket->price + $ticket->total_booking_fee,
                    'dates'                => $ticket_dates,
                ];

                //dd($tickets);
                /*
                 * To escape undefined offset errors due to accessing arrays that associate with tickets but shorter, in
                 * EventCreateOrderSection.blade, we have to nullify all extra elements... null is set to empty string
                 * denoted by ''
                 */
                $discount[count($discount)]  = '';
                $discount_ticket_title[count($discount_ticket_title)] = '';
                $exact_amount[count($exact_amount)]  = '';
                $amount_ticket_title[count($amount_ticket_title)] = '';

                /*
                 * Reserve the tickets for X amount of minutes
                 */
                $reservedTickets = new ReservedTickets();
                $reservedTickets->ticket_id = $ticket_id;
                $reservedTickets->event_id = $event_id;
                $reservedTickets->quantity_reserved = $current_ticket_quantity;
                $reservedTickets->expires = $availables['expires'];
                $reservedTickets->session_id = session()->getId();
                $reservedTickets->save();

            //} //end-foreach($ticket_ids)
        //} //end-if-!empty($ticket_ids)

        /*
         * We have to update the tickets to be reserved
         */
 //not        $reservedTickets = $availables['reserved_tickets_id'] + $reservedTickets->id;

        if (empty($tickets)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tickets selected.',
            ]);
        }

        /*
         * The 'ticket_order_{event_id}' session stores everything we need to complete the transaction. We have to update
         * the variables we had set earlier but are now modified
         */

        $availables['tickets'] = $tickets;
        $availables['total_ticket_quantity'] = $total_ticket_quantity;
 //        $availables['reserved_tickets_id'] = $reservedTickets;
        $availables['order_total'] = $order_total;
        $availables['organiser_booking_fee'] = $organiser_booking_fee;
        $availables['total_booking_fee'] = $booking_fee + $organiser_booking_fee;
        $availables['booking_fee'] = $booking_fee;
        $availables['discount'] = $discount;
        $availables['discount_ticket_title'] = $discount_ticket_title;
        $availables['exact_amount'] = $exact_amount;
        $availables['amount_ticket_title'] = $amount_ticket_title;

        session()->forget('ticket_order_' . $event->id);
        session()->set('ticket_order_' . $event->id,
            $availables
        );
       // dd($tickets);

        /*
         * If we're this far assume everything is OK and redirect them
         * to the the checkout page.
         */
    //     return response()->redirectToRoute('OrderSideEvents', [
    //         'event_id'          => $event_id
    //     ]);

        //$printer = session()->get('ticket_order_' . $event->id);
    //    dd($printer);

        /*
         * If we're this far assume everything is OK and redirect them
         * to the the checkout page.
         */
        if ($request->ajax()) {
        //    return redirect()->route('OrderSideEvents', ['event_id' => $event_id,'is_embedded' => $this->is_embedded,]). '#order_form';
            return response()->json([
                'status'      => 'success',
                'redirectUrl' => route('OrderSideEvents', [
                        'event_id'    => $event_id,
                        'is_embedded' => $this->is_embedded,
                    ]) . '#order_form',
            ]);
        }

        return redirect(route('OrderSideEvents',['event_id'=>$event_id]));

        /*
         * Maybe display something prettier than this?
         */
        //exit('Please enable Javascript in your browser.');
        return $this->javascriptError($event_id);
    }



    /**
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function postOrderAccommodation(Request $request, $event_id)
    {

        $event = Event::findOrFail($event_id);

        $order_session['order_total'] = $request->get('old_total') + $request->get('days') * $request->get('price');

        //Get the values from the fields
        $fullname = $request->get('first_name'). $request->get('last_name');
        $email = $request->get('email');
        $hotel_status = $request->get('hotel_status');
        $title = $request->get('title');
        $amount =  $order_session['order_total'];
        $price = $request->get('price');
        $ticket_id = $request->get('ticket_id');
        $accommodation_dates =  $request->get('mydates');


        //Checks to make sure only values that are filed are counted as days
        $accommodation_days = 0; $trueAccommodation_dates = [];
        foreach ($accommodation_dates as $accommodation_date) {

         if(!empty($accommodation_date)){
         ++$accommodation_days; $trueAccommodation_dates[] = $accommodation_date;
         }
        }

        //Retrieve the old Total
        $old_total = $request->get('old_total');

        //Make calculations of the new total
        $new_total = $old_total + ($accommodation_days*$price);

        //dd("Old total was ". $old_total . " and the New Total is ". $new_total);

        //TODO Clean up this section
        //$secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);
        $accomodations = Ticket::where('type','Extra')->get();
       /*
         * Remove any tickets the user has reserved
         */
    //    ReservedTickets::where('session_id', '=', session()->getId())->delete();

        /*
         * Go though the selected tickets and check if they're available
         * , tot up the price and reserve them to prevent over selling.
         */

        $availables              =    session()->get('ticket_order_' . $event_id);
        $tickets                 =    $availables['tickets'];
        $order_total             =    $availables['order_total'];
        $total_ticket_quantity   =    $availables['total_ticket_quantity'];
        $booking_fee             =    $availables['booking_fee'];
        $organiser_booking_fee   =    $availables['organiser_booking_fee'];
        $discount                =    $availables['discount'];
        $discount_ticket_title   =    $availables['discount_ticket_title'];
        $exact_amount            =    $availables['exact_amount'];
        $amount_ticket_title     =    $availables['amount_ticket_title'];
        $quantity_available_validation_rules = [];

        //dd("Order total from session is " .$order_total . "Order total from form is " . $old_total);
        //dd($tickets);

        //Checks if there are any tickets selected
        //TODO make sure the check works
       // if(!empty($ticket_ids)){
           // foreach ($ticket_ids as $ticket_id) {
               //Gets the Ticket Quantity
                //$current_ticket_quantity = (int)$request->get('ticket_' . $ticket_id);
                $current_ticket_quantity = $accommodation_days;

                /*
                if ($current_ticket_quantity < 1) {
                    continue;
                }
                */

               // dd($availables);
                //Updates the ticket quantity
                $total_ticket_quantity = $total_ticket_quantity + $current_ticket_quantity;

                //Retrieves ticket information from the database
                //dd($ticket_id);
                $ticket = Ticket::find($ticket_id);

                //
                $ticket_quantity_remaining = $ticket->quantity_remaining;

                //
                $max_per_person = min($ticket_quantity_remaining, $ticket->max_per_person);

                //
                $quantity_available_validation_rules['ticket_' . $ticket_id] = [
                    'numeric',
                    'min:' . $ticket->min_per_person,
                    'max:' . $max_per_person
                ];
               // dd($order_total);

                //
                /*
                $quantity_available_validation_messages = [
                    'ticket_' . $ticket_id . '.max' => 'The maximum number of tickets you can register is ' . $ticket_quantity_remaining,
                    'ticket_' . $ticket_id . '.min' => 'You must select at least ' . $ticket->min_per_person . ' tickets.',
                ];
                */
                /*

                $validator = Validator::make(['ticket_' . $ticket_id => (int)$request->get('ticket_' . $ticket_id)],
                    $quantity_available_validation_rules, $quantity_available_validation_messages);

                if ($validator->fails()) {
                    return response()->json([
                        'status'   => 'error',
                        'messages' => $validator->messages()->toArray(),
                    ]);
                }
                */

                $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
                //dd($order_total);
                $booking_fee = $booking_fee + ($current_ticket_quantity * $ticket->booking_fee);
                $organiser_booking_fee = $organiser_booking_fee + ($current_ticket_quantity * $ticket->organiser_booking_fee);

                //Appends Ticket information to the Ticket Variable that will be stored in the session
                $tickets[count($tickets)] = [
                    'ticket'                => $ticket,
                    'qty'                   => $current_ticket_quantity,
                    'price'                 => ($current_ticket_quantity * $ticket->price),
                    'booking_fee'           => ($current_ticket_quantity * $ticket->booking_fee),
                    'organiser_booking_fee' => ($current_ticket_quantity * $ticket->organiser_booking_fee),
                    'full_price'            => $ticket->price + $ticket->total_booking_fee,
                    'dates'                => $trueAccommodation_dates,
                ];

                //dd($tickets);
                /*
                 * To escape undefined offset errors due to accessing arrays that associate with tickets but shorter, in
                 * EventCreateOrderSection.blade, we have to nullify all extra elements... null is set to empty string
                 * denoted by ''
                 */
                $discount[count($discount)]  = '';
                $discount_ticket_title[count($discount_ticket_title)] = '';
                $exact_amount[count($exact_amount)]  = '';
                $amount_ticket_title[count($amount_ticket_title)] = '';

                /*
                 * Reserve the tickets for X amount of minutes
                 */
                $reservedTickets = new ReservedTickets();
                $reservedTickets->ticket_id = $ticket_id;
                $reservedTickets->event_id = $event_id;
                $reservedTickets->quantity_reserved = $current_ticket_quantity;
                $reservedTickets->expires = $availables['expires'];
                $reservedTickets->session_id = session()->getId();
                $reservedTickets->save();

            //} //end-foreach($ticket_ids)
        //} //end-if-!empty($ticket_ids)

        /*
         * We have to update the tickets to be reserved
         */
//not        $reservedTickets = $availables['reserved_tickets_id'] + $reservedTickets->id;

        if (empty($tickets)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tickets selected.',
            ]);
        }

        /*
         * The 'ticket_order_{event_id}' session stores everything we need to complete the transaction. We have to update
         * the variables we had set earlier but are now modified
         */

        $availables['tickets'] = $tickets;
        $availables['total_ticket_quantity'] = $total_ticket_quantity;
//        $availables['reserved_tickets_id'] = $reservedTickets;
        $availables['order_total'] = $order_total;
        $availables['organiser_booking_fee'] = $organiser_booking_fee;
        $availables['total_booking_fee'] = $booking_fee + $organiser_booking_fee;
        $availables['booking_fee'] = $booking_fee;
        $availables['discount'] = $discount;
        $availables['discount_ticket_title'] = $discount_ticket_title;
        $availables['exact_amount'] = $exact_amount;
        $availables['amount_ticket_title'] = $amount_ticket_title;

        session()->forget('ticket_order_' . $event->id);
        session()->set('ticket_order_' . $event->id,
            $availables
        );
       // dd($tickets);

        /*
         * If we're this far assume everything is OK and redirect them
         * to the the checkout page.
         */
         return response()->redirectToRoute('OrderAccommodation', [
             'event_id'          => $event_id
         ]);

        //$printer = session()->get('ticket_order_' . $event->id);
    //    dd($printer);

        /*
         * If we're this far assume everything is OK and redirect them
         * to the the checkout page.
         */
        if ($request->ajax()) {
            return response()->json([
                'status'      => 'success',
                'redirectUrl' => route('OrderAccommodation', [
                        'event_id'    => $event_id,
                        'is_embedded' => $this->is_embedded,
                    ]) . '#order_form',
            ]);
        }

        /*
         * Maybe display something prettier than this?
         */
        //exit('Please enable Javascript in your browser.');
        return $this->javascriptError($event_id);
    }


    /**
     * Show the checkout page
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showEventCheckout(Request $request, $event_id)
    {
        $order_session = session()->get('ticket_order_' . $event_id);

        if (!$order_session || $order_session['expires'] < Carbon::now()) {
            $route_name = $this->is_embedded ? 'showEmbeddedEventPage' : 'showEventPage';
            session()->forget('ticket_order_' . $event_id);
            return redirect()->route($route_name, ['event_id' => $event_id]);
        }
        //for skipping payment
        if($order_session['order_total'] + $order_session['donation'] == 0 && count($order_session['order_has_validdiscount'])>0){

        session()->set('transaction_'.$event_id,'complete');
        return $this->organiserSkipPayment($event_id);
        }

        $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);

        $data = $order_session + [
                'event'           => Event::findorFail($order_session['event_id']),
                'secondsToExpire' => $secondsToExpire,
                'coupon_flag'           => $order_session['coupon_flag'],
                'discount'              => $order_session['discount'],
                'newSubTotal'              => '',
                'first_name'              => $order_session['first_name'],
                'last_name'              => $order_session['last_name'],
                'email'              => $order_session['email'],
                'discount_ticket_title' => $order_session['discount_ticket_title'],
                'exact_amount'          => $order_session['exact_amount'],
                'amount_ticket_title'   => $order_session['amount_ticket_title'],
                'is_embedded'     => $this->is_embedded,
            ];

        session()->set('transaction_'.$event_id,'payments');

        return view('Public.ViewEvent.EventPageCheckout', $data);
/*
        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventPageCheckout', $data);
        }
*/

    }

    public function eventCheckoutAlternative($event_id)
    {
        $order_session = session()->get('ticket_order_' . $event_id);

        $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);

        $data = $order_session + [
                'event'           => Event::findorFail($order_session['event_id']),
                'secondsToExpire' => $secondsToExpire,
                'coupon_flag'           => $order_session['coupon_flag'],
                'discount'              => $order_session['discount'],
                'newSubTotal'              => '',
                'first_name'              => $order_session['first_name'],
                'last_name'              => $order_session['last_name'],
                'email'              => $order_session['email'],
                'discount_ticket_title' => $order_session['discount_ticket_title'],
                'exact_amount'          => $order_session['exact_amount'],
                'amount_ticket_title'   => $order_session['amount_ticket_title'],
                'is_embedded'     => $this->is_embedded,
            ];

        session()->set('transaction_'.$event_id,'payments');

        return view('Public.ViewEvent.EventPageCheckout', $data);
    }

    public function finishCreatingOrder($event_id)
    {
    //    return redirect(route('postCreateOrder',['event_id'=>$event_id]));
        /*
         * If there's no session kill the request and redirect back to the event homepage.
         */
        if (!session()->get('ticket_order_' . $event_id)) {
            return $this->sessionExpiredError($event_id,'showEventPage');
        }

        return $this->completeOrder($event_id);
    }

    public function testautopaypal($event_id){
        return redirect()->route('postPayment',['event_id'=>$event_id]);
    }

    /**
     * Create the order, handle payment, update stats, fire off email jobs then redirect user
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse
     */
     public function postCreateOrder(Request $request, $event_id)
    {
        
        if(substr(URL::previous(),-21)=='/transactions/control'){
            session()->set('transaction_'.$event_id,'pdfs');
            if (!session()->get('ticket_order_' . $event_id)) {
                return $this->sessionExpiredError($event_id,'showEventPage');
            }
            $event = Event::findOrFail($event_id);
            $ticket_order = session()->get('ticket_order_' . $event_id);
            if(isset($ticket_order['past_order_id'])){
                goto skip_validation;
            }
            $order = new Order;
            $validation_rules = $ticket_order['validation_rules'];
            $validation_messages = $ticket_order['validation_messages'];
            $order->rules = array_merge($order->rules,[$validation_rules]);
            $order->messages = array_merge($order->messages,[$validation_messages]);
            if (!$order->validate($request->all()) && !isset($ticket_order['donation'])) {
                $data = [
                    'event' => $event,
                    'callbackurl' => 'createorder',
                    'messages' => $order->errors(),
                    'request_details' => $request,
                    'parameters' => ['event_id' => $event_id]
                ];
                return view('Public.ViewEvent.EventPageErrors', $data);
            }
            skip_validation:
            session()->push('ticket_order_' . $event_id . '.request_data', $request->all()/*->except(['tracking_id', 'merchant_reference'])*/);
        //for skipping payment
        $order_session = session()->get('ticket_order_' . $event_id);
        if($order_session['order_total'] + $order_session['donation'] == 0 && count($order_session['order_has_validdiscount'])>0){

        session()->set('transaction_'.$event_id,'complete');
        return $this->completeOrder($event_id);
        }else{
        //    return redirect(route('showEventCheckout',['event_id'=>$event_id]));
        //    return $this->showEventCheckout($request, $event_id);
            return $this->testautopaypal($event_id);
        //    return $this->eventCheckoutAlternative($event_id);
        }

        }

        /*
         * If there's no session kill the request and redirect back to the event homepage.
         */
        if (!session()->get('ticket_order_' . $event_id)) {
            /*return response()->json([
                'status'      => 'error',
                'message'     => 'Your session has expired.',
                'redirectUrl' => route('showEventPage', [
                    'event_id' => $event_id,
                ])
            ]);*/
            return $this->sessionExpiredError($event_id,'showEventPage');
        }
        //dd("I am here");
        $event = Event::findOrFail($event_id);
        $ticket_order = session()->get('ticket_order_' . $event_id);
        //dd($ticket_order);
        //skip validation if its editing the order
        if(isset($ticket_order['past_order_id'])){
            goto done_with_validation;
        }
        $order = new Order;
        $validation_rules = $ticket_order['validation_rules'];
        $validation_messages = $ticket_order['validation_messages'];
        $order->rules = array_merge($order->rules,[$validation_rules]);
        $order->messages = array_merge($order->messages,[$validation_messages]);
        if (!$order->validate($request->all()) && !isset($ticket_order['donation'])) {
            /*return response()->json([
                'status'   => 'error',
                'messages' => $order->errors(),
            ]);*/
            $data = [
                'event' => $event,
                'callbackurl' => 'createorder',
                'messages' => $order->errors(),
                'request_details' => $request,
                'parameters' => ['event_id' => $event_id]
            ];
            return view('Public.ViewEvent.EventPageErrors', $data);
        }
        done_with_validation:
        /*
         * Add the request data to a session in case payment is required off-site
         */
        //session()->push('ticket_order_' . $event_id . '.request_data', $request->except(['card-number', 'card-cvc']));
        session()->push('ticket_order_' . $event_id . '.request_data', $request/*->except(['tracking_id', 'merchant_reference'])*/);

        return $this->completeOrder($event_id);
        //this section was re-commented by Donald on Sat 20, 2018 at 3:34 pm
        /*
         * Begin payment attempt before creating the attendees etc.
         * */
    //    if ($ticket_order['order_requires_payment']) {




    $transaction = '{';
    foreach ($transaction_data as $key => $value) {
        $transaction = $transaction.'"'.$key.'":"'.$value.'",';
    }

    $transaction = substr($transaction,0,strlen($transaction)-1).'}';
/*        $transaction = $gateway->purchase($transaction_data);
    $response = $transaction->send();
    if ($response->isSuccessful()) {
        session()->push('ticket_order_' . $event_id . '.transaction_id',
            $response->getTransactionReference());
*/            session()->push('ticket_order_' . $event_id . '.transaction_id', session()->get('tracking_id'));

        return $this->completeOrder($event_id);


            if ($error) {
                /*return response()->json([
                    'status'  => 'error',
                    'message' => $error,
                ]);*/
            $data = [
                'event' => $event,
                'callbackurl' => null,
                'messages' => $error,
                'request_details' => null,
                'parameters' => null
            ];
            return view('Public.ViewEvent.EventPageErrors', $data);
            }
    //    }
        /*
         * No payment required so go ahead and complete the order
         */
        return $this->completeOrder($event_id);
    }


    /**
     * Attempt to complete a user's payment when they return from
     * an off-site gateway
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function showEventCheckoutPaymentReturn(Request $request, $event_id)
    {

        if ($request->get('is_payment_cancelled') == '1') {
            session()->flash('message', 'You cancelled your payment. You may try again.');
            return response()->redirectToRoute('showEventCheckout', [
                'event_id'             => $event_id,
                'is_payment_cancelled' => 1,
            ]);
        }

        $ticket_order = session()->get('ticket_order_' . $event_id);
        $gateway = Omnipay::create($ticket_order['payment_gateway']->name);

        $gateway->initialize($ticket_order['account_payment_gateway']->config + [
                'testMode' => config('attendize.enable_test_payments'),
            ]);

        $transaction = $gateway->completePurchase($ticket_order['transaction_data'][0]);

        $response = $transaction->send();

        if ($response->isSuccessful()) {
            session()->push('ticket_order_' . $event_id . '.transaction_id', $response->getTransactionReference());
            return $this->completeOrder($event_id, false);
        } else {
            session()->flash('message', $response->getMessage());
            return response()->redirectToRoute('showEventCheckout', [
                'event_id'          => $event_id,
                'is_payment_failed' => 1,
            ]);
        }

    }

    /**
     * Complete an order
     *
     * @param $event_id
     * @param bool|true $return_json
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function completeOrder($event_id, $return_json = true)
    {

        DB::beginTransaction();

        try {

            $ticket_order = session()->get('ticket_order_' . $event_id);
            //dd($ticket_order['request_data'][0]);
            $request_data = $ticket_order['request_data'][0];
            $event = Event::findOrFail($ticket_order['event_id']);
            $attendee_increment = 1;
            $ticket_questions = isset($request_data['ticket_holder_questions']) ? $request_data['ticket_holder_questions'] : [];

            //check if it is updating of the past order
            if(isset($ticket_order['past_order_id'])){
                $order=Order::findOrFail($ticket_order['past_order_id']);
                $order->amount += $ticket_order['order_total'];
                $order->notes = $ticket_order['order_subscription'] > $order->notes ? $ticket_order['order_subscription'] : $order->notes;
                $order->booking_fee += $ticket_order['booking_fee'];
                $order->organiser_booking_fee += $ticket_order['organiser_booking_fee'];
                $order->save();
                goto finished_working_on_order;
            }

            /*
             * Create the order
             */
            $order = new Order();
            if (isset($ticket_order['transaction_id'])) {
                $order->transaction_id = $ticket_order['transaction_id'][0];
            }
            if ($ticket_order['order_requires_payment'] && !isset($request_data['pay_offline'])) {
                $order->payment_gateway_id = $ticket_order['payment_gateway']->id;
            }
            $order->first_name = $request_data['order_first_name'];
            $order->last_name = $request_data['order_last_name'];
            $order->email = $request_data['order_email'];
            $order->order_status_id = isset($request_data['pay_offline']) ? config('attendize.order_awaiting_payment') : config('attendize.order_complete');
            $order->amount = $ticket_order['order_total'];
            $order->notes = $ticket_order['order_subscription'];
            $order->booking_fee = $ticket_order['booking_fee'];
            $order->organiser_booking_fee = $ticket_order['organiser_booking_fee'];
            $order->discount = 0.00;
            $order->account_id = $event->account->id;
            $order->event_id = $ticket_order['event_id'];
            $order->is_payment_received = isset($request_data['pay_offline']) ? 0 : 1;
            $order->save();

            finished_working_on_order: //important label. Skipper for editing order
            /*
             * Update the event sales volume
             */
            $event->increment('sales_volume', $order->amount);
            $event->increment('organiser_fees_volume', $order->organiser_booking_fee);

            /*
             * Update affiliates stats stats
             */
            if ($ticket_order['affiliate_referral']) {
                $affiliate = Affiliate::where('name', '=', $ticket_order['affiliate_referral'])
                    ->where('event_id', '=', $event_id)->first();
                $affiliate->increment('sales_volume', $order->amount + $order->organiser_booking_fee);
                $affiliate->increment('tickets_sold', $ticket_order['total_ticket_quantity']);
            }

            /*
             * Update the event stats
             */
            $event_stats = EventStats::firstOrNew([
                'event_id' => $event_id,
                'date'     => DB::raw('CURRENT_DATE'),
            ]);
            $event_stats->increment('tickets_sold', $ticket_order['total_ticket_quantity']);

            if ($ticket_order['order_requires_payment']) {
                $event_stats->increment('sales_volume', $order->amount);
                $event_stats->increment('organiser_fees_volume', $order->organiser_booking_fee);
            }

            /*
             * Add the attendees
             */
            foreach ($ticket_order['tickets'] as $attendee_details) {

                /*
                 * Update ticket's quantity sold
                 */
                $ticket = Ticket::findOrFail($attendee_details['ticket']['id']);

                /*
                 * Update some ticket info
                 */
                $ticket->increment('quantity_sold', $attendee_details['qty']);
                $ticket->increment('sales_volume', ($attendee_details['ticket']['price'] * $attendee_details['qty']));
                $ticket->increment('organiser_fees_volume',
                    ($attendee_details['ticket']['organiser_booking_fee'] * $attendee_details['qty']));


                /*
                 * Insert order items (for use in generating invoices)
                 */
                $orderItem = new OrderItem();
                $orderItem->title = $attendee_details['ticket']['title'];
                $orderItem->quantity = $attendee_details['qty'];
                $orderItem->order_id = $order->id;
                $orderItem->unit_price = $attendee_details['ticket']['price'];
                $orderItem->unit_booking_fee = $attendee_details['ticket']['booking_fee'] + $attendee_details['ticket']['organiser_booking_fee'];
                $orderItem->save();

                /*
                 * Create the attendees
                 */
                for ($i = 0; $i < $attendee_details['qty']; $i++) {

                    $attendee = new Attendee();
                    $attendee->first_name = $request_data["ticket_holder_first_name"][$i][$attendee_details['ticket']['id']];
                    $attendee->last_name = $request_data["ticket_holder_last_name"][$i][$attendee_details['ticket']['id']];
                    $attendee->email = $request_data["ticket_holder_email"][$i][$attendee_details['ticket']['id']];
                    $attendee->event_id = $event_id;
                    $attendee->order_id = $order->id;
                    $attendee->ticket_id = $attendee_details['ticket']['id'];
                    $attendee->account_id = $event->account->id;
                    if(isset($request_data["ticket_holder_schedule"][$i][$attendee_details['ticket']['id']])){
                        $attendee->period = $request_data["ticket_holder_schedule"][$i][$attendee_details['ticket']['id']];
                    }elseif(isset($request_data["ticket_holder_bookdays"][$i][$attendee_details['ticket']['id']])){
                        $booked_days_holder = $request_data["ticket_holder_bookdays"][$i][$attendee_details['ticket']['id']];
                        $single_days=explode(',', $booked_days_holder);
                        $attendee->period = $single_days[$i];
                    }else{
                        $attendee->period = null;
                    }
                    $attendee->reference_index = $attendee_increment;
                    $attendee->save();


                    /*
                     * Save the attendee's questions
                     */
                    foreach ($attendee_details['ticket']->questions as $question) {


                        $ticket_answer = isset($ticket_questions[$attendee_details['ticket']->id][$i][$question->id]) ? $ticket_questions[$attendee_details['ticket']->id][$i][$question->id] : null;

                        if (is_null($ticket_answer)) {
                            continue;
                        }

                        /*
                         * If there are multiple answers to a question then join them with a comma
                         * and treat them as a single answer.
                         */
                        $ticket_answer = is_array($ticket_answer) ? implode(', ', $ticket_answer) : $ticket_answer;

                        if (!empty($ticket_answer)) {
                            QuestionAnswer::create([
                                'answer_text' => $ticket_answer,
                                'attendee_id' => $attendee->id,
                                'event_id'    => $event->id,
                                'account_id'  => $event->account->id,
                                'question_id' => $question->id
                            ]);

                        }
                    }


                    /* Keep track of total number of attendees */
                    $attendee_increment++;
                }
            }

    //added by DonaldFeb13 DonaldApril27
    if($ticket_order['donation']>0){
        if(isset($ticket_order['past_order_id'])){
            $orderItem = OrderItem::where(['order_id'=>$ticket_order['past_order_id'],'title'=>'Donation'])->first();

            if($orderItem){
            $orderItem->unit_price += $ticket_order['donation'];
            $orderItem->save();
            }
            else {
             $orderItem = new OrderItem();
             $orderItem->title = 'Donation';
             $orderItem->quantity = 1;
             $orderItem->order_id = $order->id;
             $orderItem->unit_price = $ticket_order['donation'];
             $orderItem->unit_booking_fee = 0;
             $orderItem->save();
            }

        }else{
            $orderItem = new OrderItem();
            $orderItem->title = 'Donation';
            $orderItem->quantity = 1;
            $orderItem->order_id = $order->id;
            $orderItem->unit_price = $ticket_order['donation'];
            $orderItem->unit_booking_fee = 0;
            $orderItem->save();
        }
    }
    if(count($ticket_order['order_has_validdiscount'])>0){
        foreach($ticket_order['order_has_validdiscount'] as $assoctickeid=>$usedcouponcode){
            $couponobj = Coupon::where('coupon_code','=', $usedcouponcode)->first();
            $couponobj->user = $order->id;
            $couponobj->state = 'Used';
            $couponobj->save();
        }
    }
    //end of addition DonaldFeb13


            /*
             * Kill the session
             */
            session()->forget('ticket_order_' . $event->id);

            /*
             * Queue up some tasks - Emails to be sent, PDFs etc.
             */
            Log::info('Firing the event');
            event(new OrderCompletedEvent($order));


        } catch (Exception $e) {

            Log::error($e);
            DB::rollBack();


        $errordata = [
            'event' => $event,
            'callbackurl' => null,
            'messages' => 'Sorry, something beyond our realization has gone wrong while trying processing your order. Please try again',
            'request_details' => null,
            'parameters' => null
        ];
        return view('Public.ViewEvent.EventPageErrors', $errordata);

            /*return response()->json([
                'status'  => 'error',
                'message' => 'Whoops! There was a problem processing your order. Please try again.'
            ]);*/

        }

        DB::commit();

        /*if ($return_json) {
            return response()->json([
                'status'      => 'success',
                'redirectUrl' => route('showOrderDetails', [
                    'is_embedded'     => $this->is_embedded,
                    'order_reference' => $order->order_reference,
                ]),
            ]);
        }*/


        return response()->redirectToRoute('showOrderDetails', [
            'is_embedded'     => $this->is_embedded,
            'order_reference' => $order->order_reference,
        ]);


    }


    /**
     * Show the create Side Event Booking modal
     *
     * @param $event_id
     * @return \Illuminate\Contracts\View\View
     */
    public function showBookSideEvent($event_id, $ticket_id)
    {
        return view('Public.ViewEvent.Modals.CreateTicket', [
            'ticket' => Ticket::find($ticket_id),
        ]);
    }


    /**
     * Show the order details page
     *
     * @param Request $request
     * @param $order_reference
     * @return \Illuminate\View\View
     */
    public function showOrderDetails(Request $request, $order_reference)
    {
        $order = Order::where('order_reference', '=', $order_reference)->first();

        if (!$order) {
            abort(404);
        }

        $data = [
            'order'       => $order,
            'event'       => $order->event,
            'tickets'     => $order->event->tickets,
            'is_embedded' => $this->is_embedded,
        ];

        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventPageViewOrder', $data);
        }

        return view('Public.ViewEvent.EventPageViewOrder', $data);
    }

    /**
     * Shows the tickets for an order - either HTML or PDF
     *
     * @param Request $request
     * @param $order_reference
     * @return \Illuminate\View\View
     */
    public function showOrderTickets(Request $request, $order_reference)
    {
        $order = Order::where('order_reference', '=', $order_reference)->first();

        if (!$order) {
            abort(404);
        }

        $data = [
            'order'     => $order,
            'event'     => $order->event,
            'tickets'   => $order->event->tickets,
            'attendees' => $order->attendees,
            'css'       => file_get_contents(public_path('assets/stylesheet/ticket.css')),
            'image'     => base64_encode(file_get_contents(public_path($order->event->organiser->full_logo_path))),

        ];

        if ($request->get('download') == '1') {
            return PDF::html('Public.ViewEvent.Partials.PDFTicket', $data, 'Tickets');
        }
        return view('Public.ViewEvent.Partials.PDFTicket', $data);
    }
    public function showInvitationLetters(Request $request, $order_reference)
    {
        $order = Order::where('order_reference', '=', $order_reference)->first();

        if (!$order) {
            abort(404);
        }

        $data = [
            'order'     => $order,
            'event'     => $order->event,
            'tickets'   => $order->event->tickets,
            'attendees' => $order->attendees,
            'css'       => file_get_contents(public_path('assets/stylesheet/ticket.css')),
            'image'     => base64_encode(file_get_contents(public_path($order->event->organiser->full_logo_path))),

        ];

        if ($request->get('download') == '1') {
            return PDF::html('Public.ViewEvent.Partials.EventInvitationLetter', $data, 'Tickets');
        }
        return view('Public.ViewEvent.Partials.EventInvitationLetter', $data);
    }


    public function completeOrderAccommodation($event_id)
    {
        session()->set('transaction_'.$event_id,'accommodation');
        return redirect(route('handleTransactions',['event_id'=>$event_id]));
    }

    /**
     * Deleted tickets from the Accommodation page
     *
     * @param Request $request
     * @param $delete_ticket
     * @return \Illuminate\View\View
     **/

    public function removeOrderTicket(Request $request, $event_id,$page, $delete_ticket){

     $availables              =    session()->get('ticket_order_' . $event_id);

     $tickets                 =    $availables['tickets'];
     $order_total             =    $availables['order_total'];
     $total_ticket_quantity   =    $availables['total_ticket_quantity'];
     $booking_fee             =    $availables['booking_fee'];
     $organiser_booking_fee   =    $availables['organiser_booking_fee'];
     $discount                =    $availables['discount'];
     $discount_ticket_title   =    $availables['discount_ticket_title'];
     $exact_amount            =    $availables['exact_amount'];
     $amount_ticket_title     =    $availables['amount_ticket_title'];
     $quantity_available_validation_rules = [];

     $counter = 0;

     foreach($availables['tickets'] as $ordered_ticket){
      if($ordered_ticket['ticket']['id'] == $delete_ticket ){

       $remove_ticket_qty = $ordered_ticket['qty'];
       $remove_ticket_amount = $ordered_ticket['price'];

       //remove the discount entry from ordervaliddiscount if it has a discount
       if(array_key_exists($delete_ticket, $availables['order_has_validdiscount'])){
          $remove_ticket_amount -= $ordered_ticket['price'] * $discount[$counter] * 0.01;
          unset($availables['order_has_validdiscount'][$delete_ticket]);
       }

       //reduce the amount of tickets
       $availables['total_ticket_quantity'] = $availables['total_ticket_quantity'] - $remove_ticket_qty;

       //reduce the total price
       $availables['order_total'] = $availables['order_total'] - $remove_ticket_amount;

       //Remove the deleted ticket from the array
       unset($availables['tickets'][$counter]);

       //Reindex the array
       $availables['tickets'] = array_values($availables['tickets']);

      }
     ++$counter;
     }

     //Reset the session
     session()->forget('ticket_order_' . $event_id);
     session()->set('ticket_order_' . $event_id, $availables);

     if($page == "accommodation"){
           return response()->redirectToRoute('OrderAccommodation', [
               'event_id'          => $event_id
           ]);
     }elseif ($page == "workshops") {
      return response()->redirectToRoute('OrderWorkshops', [
          'event_id'          => $event_id
      ]);
     }elseif ($page == "sideevents") {
      return response()->redirectToRoute('OrderSideEvents', [
          'event_id'          => $event_id
      ]);
     }
     elseif ($page == "checkout"){
      return response()->redirectToRoute('showEventCheckout', [
          'event_id'          => $event_id
      ]);
     }
    }

}

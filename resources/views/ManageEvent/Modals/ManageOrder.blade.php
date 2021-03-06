<div role="dialog"  class="modal fade" style="display: none;">
    <style>
        .well.nopad {
            padding: 0px;
        }
    </style>

    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header text-center">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h3 class="modal-title">
                    <i class="ico-cart"></i>
                    Order: <b>{{$order->order_reference}}</b></h3>
            </div>
            <div class="modal-body">

                @if($order->is_refunded || $order->is_partially_refunded)
                 <div class="alert alert-info">
                   {{money($order->amount_refunded, $order->event->currency)}} of this order has been refunded.
                </div>
                @endif

                @if(!$order->is_payment_received)
                    <div class="alert alert-info">
                        This order is awaiting payment.
                    </div>
                    <a data-id="{{ $order->id }}" data-route="{{ route('postMarkPaymentReceived', ['order_id' => $order->id]) }}" class="btn btn-primary btn-sm markPaymentReceived" href="javascript:void(0);">Mark Payment Received</a>
                @endif

                <h3>Order Overview</h3>
                <style>
                    .order_overview b {
                        text-transform: uppercase;
                    }
                    .order_overview .col-sm-4 {
                        margin-bottom: 10px;
                    }
                </style>
                <div class="p0 well bgcolor-white order_overview">
                    <div class="row">
                        <div class="col-sm-6 col-xs-6">
                            <b>First Name</b><br> {{$order->first_name}}
                        </div>
                        <div class="col-sm-6 col-xs-6">
                            <b>Last Name</b><br> {{$order->last_name}}
                        </div>

                        <div class="col-sm-6 col-xs-6">
                            <?php $contrib_donation=0; $assoc_donation = App\Models\OrderItem::where(['title'=>'Donation','order_id'=>$order->id])->first(); if($assoc_donation){$contrib_donation = $assoc_donation->unit_price + $assoc_donation->booking_fee; } ?>
                            <b>Amount</b><br>{{money($order->total_amount + $contrib_donation, $order->event->currency)}}
                        </div>

                        <div class="col-sm-6 col-xs-6">
                            <b>Reference</b><br> {{$order->order_reference}}
                        </div>
                        <div class="col-sm-6 col-xs-6">
                            <b>Date</b><br> {{$order->created_at->toDateTimeString()}}
                        </div>
                        <div class="col-sm-6 col-xs-6">
                            <b>Email</b><br> {{$order->email}}
                        </div>

                        @if($order->transaction_id)
                        <div class="col-sm-6 col-xs-6">
                            <b>Transaction ID</b><br> {{$order->transaction_id}}
                        </div>
                        <div class="col-sm-6 col-xs-6">
                            <b>Payment Gateway</b><br> <a href="{{ $order->payment_gateway->provider_url }}" target="_blank">{{$order->payment_gateway->provider_name}}</a>
                        </div>
                        @endif

                    </div>
                </div>

                <h3>Order Items</h3>
                <div class="well nopad bgcolor-white p0">
                    <div class="table-responsive">
                        <table class="table table-hover" >
                            <thead>
                            <th>
                                Ticket
                            </th>
                            <th>
                                Quantity
                            </th>
                            <th>
                                Price
                            </th>
                            <th>
                                Booking Fee
                            </th>
                            <th>
                                Total
                            </th>
                            </thead>
                            <tbody>
                        <!--added by DonaldFeb28-->
                        <?php $total_amt_calc = 0; ?>
                        <!--end of addition-->
                        <?php 
                            $art_tickets = [];
                            foreach ($order->orderItems as $orderItem) {
                                if($orderItem->title=='Donation'){
                                        $art_tickets['donation'] = [
                                            'quantity' => 1,
                                            'total' => $orderItem->unit_price,
                                            'title' => $orderItem->title,
                                            'price' => $orderItem->unit_price,
                                            'booking_fee' => $orderItem->unit_booking_fee
                                        ];
                                }
                            }
                            foreach($order->attendees as $order_attendee) {
                                if(!$order_attendee->is_cancelled){
                                    if(array_key_exists($order_attendee->ticket->id, $art_tickets)){
                                       $art_tickets[$order_attendee->ticket->id]['quantity'] += 1;
                                       $art_tickets[$order_attendee->ticket->id]['total'] += $order_attendee->ticket->price;
                                    }else{
                                        $art_tickets[$order_attendee->ticket->id] = [
                                            'quantity' => 1,
                                            'total' => $order_attendee->ticket->price,
                                            'title' => $order_attendee->ticket->title,
                                            'price' => $order_attendee->ticket->price,
                                            'booking_fee' => $order_attendee->ticket->booking_fee
                                        ];
                                    }
                                }
                            }
                        ?>
                            @foreach($art_tickets as $order_item)
                                <tr>
                                    <td>
                                        {{$order_item['title']}}
                                    </td>
                                    <td>
                                        {{$order_item['quantity']}}
                                    </td>
                                    <td>
                                        @if($order_item['price'] == 0)
                                        FREE
                                        @else
                                       {{money($order_item['price'], $order->event->currency)}}
                                        @endif

                                    </td>
                                    <td>
                                        @if($order_item['price'] == 0)
                                        -
                                        @else
                                        {{money($order_item['booking_fee'], $order->event->currency)}}
                                        @endif

                                    </td>
                                    <td>
                                        @if($order_item['price'] == 0)
                                        FREE
                                        @else
                                        {{money(($order_item['price'] + $order_item['booking_fee']) * ($order_item['quantity']), $order->event->currency)}}
                                        <!--added by DonaldFeb28-->
                                        <?php $total_amt_calc += ($order_item['price'] + $order_item['booking_fee']) * ($order_item['quantity']); ?>
                                        <!--end of addition DonaldFeb28-->
                                        @endif

                                    </td>
                                </tr>
                            @endforeach
                                <tr>
                                    <td>
                                    </td>
                                    <td>
                                    </td>
                                    <td>
                                    </td>
                                    <td>
                                        <b>Sub Total</b>
                                    </td>
                                    <td colspan="2">
                                        <!--edited by DonaldFeb28 replacing $order->total_amount-->
                                        {{money($total_amt_calc, $order->event->currency)}}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                    </div>
                </div>

                <h3>
                    Order Attendees
                </h3>
                <div class="well nopad bgcolor-white p0">

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                @foreach($order->attendees as $attendee)
                                <tr>
                                    <td>
                                        @if($attendee->is_cancelled)
                                        <span class="label label-warning">
                                            Cancelled
                                        </span>
                                        @endif
                                        @if($attendee->is_refunded)
                                            <span class="label label-danger">
                                                Refunded
                                            </span>
                                        @endif
                                        {{$attendee->first_name}}
                                        {{$attendee->last_name}}
                                    </td>
                                    <td>
                                        {{$attendee->email}}
                                    </td>
                                    <td>
                                        {{{$attendee->ticket->title}}}
                                        {{{$order->order_reference}}}-{{{$attendee->reference_index}}}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> <!-- /end modal body-->

            <div class="modal-footer">
                <a href="javascript:void(0);" data-modal-id="edit-order-{{ $order->id }}" data-href="{{route('showEditOrder', ['order_id'=>$order->id])}}" title="Edit Order" class="btn btn-info loadModal">
                    Edit
                </a>
            <!--    <a class="btn btn-primary" target="_blank" href="{{route('showInvitationLetters', ['order_reference' => $order->order_reference])}}?download=1">View Letters</a> -->
                <a class="btn btn-primary" target="_blank" href="{{route('showOrderTickets', ['order_reference' => $order->order_reference])}}?download=1">Print Tickets</a>
                <span class="pauseTicketSales btn btn-success" data-id="{{$order->id}}" data-route="{{route('resendOrder', ['order_id'=>$order->id])}}">Resend Tickets</span>
               {!! Form::button('Close', ['class'=>"btn modal-close btn-danger",'data-dismiss'=>'modal']) !!}
            </div>
        </div><!-- /end modal content-->
    </div>
</div>

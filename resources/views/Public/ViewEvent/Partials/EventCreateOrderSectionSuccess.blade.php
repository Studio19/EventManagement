<section id='order_form' class="row bg-white"> <!--style="margin-top: 5%"-->
    <div class="container"><br><br>
        <h1 class="section_head">
            Ticket Details
        </h1>
    </div>
    <div class="container" id='blurr'>
  <!-- Order Summary Page -->
  <div class="col-md-4 col-md-push-8">
   @include('Public.ViewEvent.Partials.OrderSummary')
  </div>
        <div class="col-md-8 col-md-pull-4">
         <!--div class="container-fluid">
                 <h3>Payment Successful. Thank you</h3><br>
         </div-->

            <div class="event_order_form">
                {!! Form::open(['url' => route('postCreateOrder', ['event_id' => $event->id]), 'onsubmit'=>"loadFunction(this)"]) !!}

                {!! Form::hidden('event_id', $event->id) !!}


                <div class="row" style="display: none;">
                    <div class="col-xs-6">
                        <div class="form-group">
                            {!! Form::label("order_first_name", 'First Name') !!}
                            {!! Form::text("order_first_name", $first_name, ['required' => 'required', 'class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            {!! Form::label("order_last_name", 'Last Name') !!}
                            {!! Form::text("order_last_name", $last_name, ['required' => 'required', 'class' => 'form-control']) !!}
                        </div>
                    </div>
                </div>

                <div class="row" style="display: none;">
                    <div class="col-md-12">
                        <div class="form-group">
                            {!! Form::label("order_email", 'Email') !!}
                            {!! Form::text("order_email", $email, ['required' => 'required', 'class' => 'form-control']) !!}
                        </div>
                    </div>
                </div>

                <div class="p20 pl0 row">
                 <div class="col-md-12">
                    <a href="javascript:void(0);" class="btn btn-danger btn-xs" id="mirror_buyer_info">
                        Click here to Copy buyers details to all tickets below
                    </a>
                 </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="ticket_holders_details" >
                            <h3>Ticket Holder Information</h3>
                            <?php $booked_days_splitter=0;//dd($tickets);
                                $total_attendee_increment = 0; //$preferedschedule=[];
                            ?>
                            @foreach($tickets as $ticket)
                                @for($i=0; $i<=$ticket['qty']-1; $i++)
                                <div class="panel panel-primary">

                                    <div class="panel-heading">
                                        <h3 class="panel-title">
                                            <b>{{$ticket['ticket']['title']}}</b>: Ticket Holder {{$i+1}} Details
                                        </h3>
                                    </div>
                                    <div class="panel-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    {!! Form::label("ticket_holder_first_name[{$i}][{$ticket['ticket']['id']}]", 'First Name') !!}
                                                    {!! Form::text("ticket_holder_first_name[{$i}][{$ticket['ticket']['id']}]", null, ['required' => 'required', 'class' => "ticket_holder_first_name.$i.{$ticket['ticket']['id']} ticket_holder_first_name form-control"]) !!}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    {!! Form::label("ticket_holder_last_name[{$i}][{$ticket['ticket']['id']}]", 'Last Name') !!}
                                                    {!! Form::text("ticket_holder_last_name[{$i}][{$ticket['ticket']['id']}]", null, ['required' => 'required', 'class' => "ticket_holder_last_name.$i.{$ticket['ticket']['id']} ticket_holder_last_name form-control"]) !!}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    {!! Form::label("ticket_holder_email[{$i}][{$ticket['ticket']['id']}]", 'Email Address') !!}
                                                    {!! Form::text("ticket_holder_email[{$i}][{$ticket['ticket']['id']}]", null, ['required' => 'required', 'class' => "ticket_holder_email.$i.{$ticket['ticket']['id']} ticket_holder_email form-control"]) !!}
                                                </div>
                                            </div>
                                            @include('Public.ViewEvent.Partials.AttendeeQuestions', ['ticket' => $ticket['ticket'],'attendee_number' => $total_attendee_increment++])

                                        </div>

                                    </div>

                                        <?php  //added by DonaldMar14
                                            /*if($ticket['ticket']['ticket_offers'] && $ticket['ticket']['type']==='SIDEEVENT'){
                                                $toffers = explode('+++',$ticket['ticket']['ticket_offers']);
                                                if(count($toffers)===1){
                                                    $sched = explode('<==>',$toffers[0]);
                                                    $option = date('d-M-Y H:i', strtotime($sched[0])).' to '.date('d-M-Y H:i', strtotime($sched[1]));
                                                    echo '<div class = \'row\'>';
                                                    echo '<p>For Schedule: '.$option.'</p>';
                                                    echo Form::hidden("ticket_holder_schedule[{$i}][{$ticket['ticket']['id']}]",$option);
                                                    echo '</div>';
                                                }else{
                                                    echo '<div class=\'row\'><b>Choose a Schedule for this ticket</b></div>';
                                                    for($radiono=0;$radiono<count($toffers);++$radiono){
                                                        $sched = explode('<==>',$toffers[$radiono]);
                                                        $option = date('d-M-Y H:i', strtotime($sched[0])).' to '.date('d-M-Y H:i', strtotime($sched[1]));
                                                        echo '<div class=\'row\'><ul>';
                                                        echo Form::radio("ticket_holder_schedule[{$i}][{$ticket['ticket']['id']}]", $option, false, ['class' => 'field']); echo $option;
                                                        echo '</ul></div>';
                                                    }
                                                }
                                            }*///DonaldMar27
                                            //DonaldApril03
                                            if(isset($ticket['dates']) && in_array($ticket['ticket']['type'],['SIDEEVENT','WORKSHOP'])){
                                                $sched = explode('<==>',$ticket['dates']);
                                                $option = date('d-M-Y H:i', strtotime($sched[0])).' to '.date('d-M-Y H:i', strtotime($sched[1]));
                                                //echo '<div class = \'row\'>';
                                                //echo '<p>For Schedule: '.$option.'</p>';
                                                echo '<div class=\'row\'><div class=\'col-md-12\'><div class=\'form-group\'>';
                                                echo Form::label(' ','For Schedule: '.$option); echo '</div></div></div>';
                                                echo Form::hidden("ticket_holder_schedule[{$i}][{$ticket['ticket']['id']}]",$option);
                                                //echo '</div>';
                                            }elseif(isset($ticket['dates'])){ //dd($ticket);
                                                echo '<div class=\'row\'><div class=\'col-md-12\'><div class=\'form-group\'>';
                                                echo Form::label(' ','Booked Day:'); echo '</div></div></div>';
                                                $days = $ticket['dates']; //$dates = [];
                                                echo '<div class=\'row\'>';
                                                    //for($daycounter=0; $daycounter<count($days); ++$daycounter){
                                                         echo '&nbsp;&nbsp;<i class=\'glyphicon glyphicon-calendar\'>'.$days[$booked_days_splitter].'</i>';
                                                         ++$booked_days_splitter;
                                                        //if($daycounter % 6 == 0){ //limit days to 6 in a row
                                                            //echo '</div><div class = \'row\'>';
                                                        //}
                                                    //}
                                                echo '</div><div class=\'row\'>&nbsp;</div>';
                                                $dateString = implode(',',$days);
                                                echo Form::hidden("ticket_holder_bookdays[{$i}][{$ticket['ticket']['id']}]",$dateString);
                                                //echo '<br>'.$dateString;
                                            }
                                            //end of addition
                                        ?>


                                </div>
                                @endfor
                            @endforeach
                        </div>
                    </div>
                </div>

                <style>
                    .offline_payment_toggle {
                        padding: 20px 0;
                    }
                </style>

               <!-- @if($order_requires_payment)

                <h3>Payment Information</h3>

                @if($event->enable_offline_payments)
                    <div class="offline_payment_toggle">
                        <div class="custom-checkbox">
                            <input data-toggle="toggle" id="pay_offline" name="pay_offline" type="checkbox" value="1">
                            <label for="pay_offline">Pay using offline method</label>
                        </div>
                    </div>
                    <div class="offline_payment" style="display: none;">
                        <h5>Offline Payment Instructions</h5>
                        <div class="well">
                            {!! Markdown::parse($event->offline_payment_instructions) !!}
                        </div>
                    </div>

                @endif


                <!-- Stripe -->
              <!-- @if(@$payment_gateway->id==1)
                   <div class="row">
                                <label class="col-md-12 text-center"><h3>Stripe</h3></label>


                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('first_name', 'First Name', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('first_name', Input::old('first_name'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('last_name', 'Last Name', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('last_name', Input::old('last_name'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                </div>
                                 <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('email', 'Email', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('email', Input::old('email'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                </div>


                @endif




                <!-- PayPal -->
             <!--  @if(@$payment_gateway->id==2)
                   <div class="row">
                                <label class="col-md-12 text-center"><h3>PayPal</h3></label>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('first_name', 'First Name', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('first_name', Input::old('first_name'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('last_name', 'Last Name', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('last_name', Input::old('last_name'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                </div>
                                 <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('email', 'Email', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('email', Input::old('email'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                </div>


                @endif






            <!-- Coinbae -->
             <!--   @if(@$payment_gateway->id==3)
                    <div class="row">
                                <label class="col-md-12 text-center"><h3>CoinBase</h3></label>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('first_name', 'SECRET CODE', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('first_name', Input::old('first_name'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            {!! Form::label('last_name', 'ACCOUNT ID', array('class'=>'control-label required')) !!}
                                            {!!  Form::text('last_name', Input::old('last_name'),
                                        array(
                                        'class'=>'form-control'
                                        ))  !!}
                                        </div>
                                    </div>
                                </div>
                @endif

            <!-- Master Card Payment
               @if(@$payment_gateway->id==4)
                    <div class="online_payment">
                        <div class="row">
                            <label class="col-md-12 text-center"><h3>MasterCard Payment</h3></label>
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('card-number', 'Card Number') !!}
                                    <input required="required" type="text" autocomplete="off" placeholder="**** **** **** ****" class="form-control card-number" size="20" data-stripe="number">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    {!! Form::label('card-expiry-month', 'Expiry Month') !!}
                                    {!!  Form::selectRange('card-expiry-month',1,12,null, [
                                            'class' => 'form-control card-expiry-month',
                                            'data-stripe' => 'exp_month'
                                        ] )  !!}
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    {!! Form::label('card-expiry-year', 'Expiry Year') !!}
                                    {!!  Form::selectRange('card-expiry-year',date('Y'),date('Y')+10,null, [
                                            'class' => 'form-control card-expiry-year',
                                            'data-stripe' => 'exp_year'
                                        ] )  !!}</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('card-expiry-year', 'CVC Number') !!}
                                    <input required="required" placeholder="***" class="form-control card-cvc" data-stripe="cvc">
                                </div>
                            </div>
                        </div>
                    </div>

                @endif


               @if(@$payment_gateway->id==5)
                   <div class="row">
                    <center>
                        <h1>Payment Successful.</h1><br>
                        <h2>Thank you...</h2><br>
                    </center>
                    </div>


                @endif

                @endif-->


                @if($event->pre_order_display_message)
                <div class="well well-small">
                    {!! nl2br(e($event->pre_order_display_message)) !!}
                </div>
                @endif

               {!! Form::hidden('is_embedded', $is_embedded) !!}
<div class="col-md-12">
               {!! Form::submit('Proceed to Payment', ['class' => 'btn btn-lg btn-success card-submit', 'style' => 'width:100%;', 'id'=>'generatorbutton']) !!}
               <!--, 'onClick'=>"this.disabled=true; this.value='Generating Your Tickets';"-->
              </div>
</br>
</br>
</br>
            </div>
        </div>
    </div>
    <div class='container' id='replacer' style="display: hidden">

    </div>
</section>
@if(session()->get('message'))
    <script>showMessage('{{session()->get('message')}}');</script>
@endif

<script>

function loadFunction(e){
    var wait = document.getElementById("generatorbutton");
    wait.disabled=true;
    wait.value="Redirecting to Paypal.";
    var dots = window.setInterval( function() {
    if ( wait.value.length > 28 )
        wait.value = "Redirecting to Paypal.";
    else
        wait.value += " .";
    }, 500);
}
</script>

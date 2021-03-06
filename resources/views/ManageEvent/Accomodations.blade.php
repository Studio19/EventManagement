@extends('Shared.Layouts.Master')

@section('title')
    @parent
    Accomodations
@stop

@section('top_nav')
    @include('ManageEvent.Partials.TopNav')
@stop

@section('page_title')
    <i class="glyphicon glyphicon-star"></i>
    Accomodations
@stop

@section('head')
    <script>
        $(function () {
            $('.sortable').sortable({
                handle: '.sortHandle',
                forcePlaceholderSize: true,
                placeholderClass: 'col-md-4 col-sm-6 col-xs-12',
            }).bind('sortupdate', function (e, ui) {
                var data = $('.sortable .ticket').map(function () {
                    return $(this).data('ticket-id');
                }).get();
                $.ajax({
                    type: 'POST',
                    url: '{{ route('postUpdateTicketsOrder' ,['event_id' => $event->id]) }}',
                    dataType: 'json',
                    data: {ticket_ids: data},
                    success: function (data) {
                        showMessage(data.message);
                    },
                    error: function (data) {
                        showMessage('Something went wrong. Please try again.');
                    }
                });
            });
        });
    </script>
@stop

@section('menu')
    @include('ManageEvent.Partials.Sidebar')
@stop

@section('page_header')
    <div class="col-md-9">
        <!-- Toolbar -->
        <div class="btn-toolbar" role="toolbar">
            <div class="btn-group btn-group-responsive">
                <button data-modal-id='CreateTicket'
                        data-href="{{route('showCreateAccomodation', array('event_id'=>$event->id))}}"
                        class='loadModal btn btn-success' type="button"><i class="ico-ticket"></i> Create an Accomodation
                </button>
            </div>
        </div>
        <!--/ Toolbar -->
    </div>
    <div class="col-md-3">
        {!! Form::open(array('url' => route('showEventTickets', ['event_id'=>$event->id]), 'method' => 'get')) !!}
        <div class="input-group">
            <input name='q' value="{{$q or ''}}" placeholder="Search for Accomodation.." type="text" class="form-control">
        <span class="input-group-btn">
            <button class="btn btn-default" type="submit"><i class="ico-search"></i></button>
        </span>

        </div>
        {!! Form::close() !!}
    </div>
@stop

@section('content')
    @if($tickets->count())
        <div class="row">
            <div class="col-md-3 col-xs-6">
                <div class='order_options'>
                    <span class="event_count">{{$tickets->count()}} tickets</span>
                </div>
            </div>
            <div class="col-md-2 col-xs-6 col-md-offset-7">
                <div class='order_options'>

                </div>
            </div>
        </div>
    @endif
    <!--Start ticket table-->
    <div class="row sortable">
        @if($tickets->count())

            @foreach($tickets as $ticket)
                <div id="ticket_{{$ticket->id}}" class="col-md-4 col-sm-6 col-xs-12">
                    <div class="panel panel-success ticket" data-ticket-id="{{$ticket->id}}">
                        <div style="cursor: pointer;" data-modal-id='ticket-{{ $ticket->id }}'
                             data-href="{{ route('showEditAccommodation', ['event_id' => $event->id, 'ticket_id' => $ticket->id]) }}"
                             class="panel-heading loadModal">
                            <h3 class="panel-title">
                                @if($ticket->is_hidden)
                                    <i title="This ticket is hidden"
                                       class="ico-eye-blocked ticket_icon mr5 ellipsis"></i>
                                @else
                                    <i class="ico-ticket ticket_icon mr5 ellipsis"></i>
                                @endif
                                {{$ticket->title}}&nbsp;&nbsp;

                                    @for($i=0; $i<$ticket->status; $i++)
                                        <i class="glyphicon glyphicon-star" style="color: #FFD700"></i>
                                    @endfor

                                <span class="pull-right">
                        {{ ($ticket->is_free) ? "FREE" : money($ticket->price, $event->currency) }}
                    </span>
                            </h3>
                        </div>
                        <div class='panel-body'>
                            <ul class="nav nav-section nav-justified mt5 mb5">
                                <li>
                                    <div class="section">
                                        <h4 class="nm">{{ $ticket->quantity_sold }}</h4>

                                        <p class="nm text-muted">Sold</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="section">
                                        <h4 class="nm">
                                            {{ ($ticket->quantity_available === null) ? '&infin;' : $ticket->quantity_remaining }}
                                        </h4>

                                        <p class="nm text-muted">Remaining</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="section">
                                        <h4 class="nm hint--top"
                                            title="{{money($ticket->sales_volume, $event->currency)}} + {{money($ticket->organiser_fees_volume, $event->currency)}} Organiser Booking Fees">
                                            {{money($ticket->sales_volume + $ticket->organiser_fees_volume, $event->currency)}}
                                            <sub title="Doesn't account for refunds.">*</sub>
                                        </h4>
                                        <p class="nm text-muted">Revenue</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="panel-footer" style="height: 56px;">
                            <div class="sortHandle" title="Drag to re-order">
                                <i class="ico-paragraph-justify"></i>
                            </div>
                            <ul class="nav nav-section nav-justified">
                                <li>
                                    <a href="javascript:void(0);">
                                        @if($ticket->sale_status === config('attendize.ticket_status_on_sale'))
                                            @if($ticket->is_paused)
                                                Ticket Sales Paused &nbsp;
                                                <span class="pauseTicketSales label label-info"
                                                      data-id="{{$ticket->id}}"
                                                      data-route="{{route('postPauseTicket', ['event_id'=>$event->id])}}">
                                    <i class="ico-play4"></i> Resume
                                </span>
                                            @else
                                                On Sale &nbsp;
                                                <span class="pauseTicketSales label label-info"
                                                      data-id="{{$ticket->id}}"
                                                      data-route="{{route('postPauseTicket', ['event_id'=>$event->id])}}">
                                    <i class="ico-pause"></i> Pause
                                </span>
                                            @endif
                                        @else
                                            {{\App\Models\TicketStatus::find($ticket->sale_status)->name}}
                                        @endif
                                    </a>
                                </li>
            <!--added by DonaldMar2-->
            <li>
                <a href="{{route('postDeleteTicket', ['ticket_id' => $ticket->id])}}" onClick="return confirm('Oh you really sure want to delete this ticket?');">
                    <i class="ico-remove"></i> Delete
                </a>
            </li>
            <!--end of addition DonaldMar2-->
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        @else

        @endif
    </div><!--/ end ticket table-->
    <div class="row">
        <div class="col-md-12">

        </div>
    </div>
@stop

<!-- Beginning of Modal -->
<div class="modal fade" id="more_details_{{$minevent->id}}" >
<div class="modal-dialog">
      <div class="modal-content">
       <div class="modal-head text-light text-center" style="background-color: #5fa9da">
        <h2> {{ $minevent->title}} </h2>
       </div>
       <!--Form::open(['url' => route('postBookSideEvent', ['event_id' => $event->id]), 'class' => 'ajax']) !!}-->
       <form action="{{ route( 'postOrderWorkshops', ['event_id'=>$event->id]) }}" method="post">

        {{ csrf_field() }}
        {!! Form::hidden('ticket_id', $minevent->id) !!}
        <div class="modal-body">
         <div class="col-md-12 container-fluid">

      <div class="form-group col-md-12" id='datetimepicker4'>
           <div class="col-sm-12">
            <div class="col-sm-12 field-label">
             <span>
              {{$minevent->description}}
             </span>
            </div>
            <div class="col-sm-9">
             <!--<input type="date" name="mydates[]" class="form-control" required>-->
            </div>
           </div>
           <div class="col-sm-12">
            <div class="col-sm-12 field-label">
             <span>
               Presented By:
             </span>
            </div>
            <div class="col-sm-12 field-label">
             <span>
              {{$minevent->ticket_extras}}
             </span>
            </div>
           </div>
          <br>
          <br>
        <div class="col-sm-12">
          <div class="row" style="text-align: center;">
            <!--div class="col-xs-4 side-event-image"-->
              <?php if($minevent->ticket_main_photo){ ?>
               <img height=180 width=150 style="margin: 5px 1px 10px 100px; align:justify" src="{{asset($minevent->ticket_main_photo)}}" />
              <?php }else{ ?>
              <img height=180 width=150 style="margin: 5px 1px 10px 100px; align:justify" src="{{asset('assets/images/default/trip.jpg')}}" />
              <?php } ?>
            <!--/div-->
          </div>
      	</div>
          <br>
          <br>
           <div class="col-sm-12">
            <div class="col-sm-12 field-label">
             <span>

              <?php if($minevent->ticket_offers!=NULL){
                       $ticket_offers = explode('+++',$minevent->ticket_offers);

                       echo "<p><b> List of available sessions </b></p>";
                       for($i=0;$i<count($ticket_offers);++$i){
                           $sched = explode('<==>',$ticket_offers[$i]);
                           $count = $i+1;
                           echo '<div class="row">';
                           echo '<p>';
                           echo date('d-M-Y H:i', strtotime($sched[0])).' to '.date('d-M-Y H:i', strtotime($sched[1])).'';
                           echo '</p>';
                           echo '</div>';
                       } ?>
             <?php } ?>

             </span>
            </div>
            <div class="col-sm-12 field-label">
            	<b>Ticket Price</b><br>
              <span><b>{{money($minevent->price, $event->currency)}}</b></span>
            </div>
            <div class="col-sm-9">
             <!--<input type="date" name="mydates[]" class="form-control" required>-->
            </div>
           </div>

           <div class="form-group col-md-12" id="dategenerator{{ $minevent->id}}">
           </div>
          </div>

          <input type="text" name="price" hidden value="{{$minevent->price}}" >
          <input type="text" name="event_id" hidden value"{{$minevent->id}}" >
          <input type="text" name="status" hidden value="{{$minevent->status}}" >
          <input type="text" name="title" hidden value="{{$minevent->title}}" >
          <input type="text" name="old_total" hidden value="{{$order_total}}" >
         </div>

        </div>
        <div class="modal-footer">
         <button class="btn btn-danger" data-dismiss="modal">Close</button>
         <!--button class="btn btn-success" type="submit" value="submit">Save</button-->
        </div>
       </form>
       <script>

       var counter = 2;
       var limit = 14;

       function addInput(divName){
        //Checkif there is a variable with the value
        if(typeof(window[divName+counter])==="undefined"){
         window[divName+counter] = counter;
        }
        var currentcounter = window[divName+counter];

        if (currentcounter == limit)  {
         alert("You have reached the limit of adding extra days");
        }
        else {
         var newdiv = document.createElement('div');
         newdiv.classList.add("row");
         newdiv.classList.add("day-row");
         newdiv.innerHTML = "<div class='col-sm-3 field-label'><label> Day " + (currentcounter) + " </label></div><div class='col-sm-9'><input type='date' class='form-control' name='mydates[]' ></div>";
         document.getElementById(divName).appendChild(newdiv);
         window[divName+counter]++;
        }
       }

       </script>
      </div>
     </div>
    </div>
<!-- End of Modal -->

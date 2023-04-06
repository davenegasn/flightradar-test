<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TicketsController extends Controller
{

    protected array $flights = [
        322 => [
            'origin'        => 'Arlanda',
            'destination'   => 'Schipol',
            'departure'     => '2023-05-11 14:30'
        ],
        431 => [
            'origin'        => 'Arlanda',
            'destination'   => 'Berlin',
            'departure'     => '2023-05-11 18:30'
        ]
    ];

    public $tickets = array();

    public function __construct()
    {
        if (Storage::exists('tickets.json')) {

            $stored = Storage::get('tickets.json');
            $tickets = json_decode($stored, true);
       
            $this->tickets = $tickets;
       
        }
        
    }

     /**
     * Get all tickets
     * 
     * 
     * @return JSON with the tickets
     */
    public function index()
    {
        return response()->json([
            'data' => $this->tickets
        ], 200);

    }

    /**
     * Add a new ticket
     * 
     * @param string $key
     * @param Request $request
     * 
     * @return JSON the created ticket
     */
    public function store(Request $request)
    {

        $rules = [
            'flight' => 'required|in:322,431',
            'passport' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 404);
        }
        
        $ticket = $this->makeTicket($request);
        
        $this->addTicket($ticket);

        return response()->json([
            'data' => $ticket
        ], 201);

    }

    /**
     * Update a ticket seat
     * 
     * @param Request $request
     * 
     * @return bool Ticket processed
     */
    public function update(Request $request)
    {

        $rules = [
            'action'    => 'required|in:seat,status',
            'ticket'    => 'required',
            'value'     => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 404);
        }

        $targetTicket = $request->ticket;
        $action = $request->action;
        $value = $request->value;
        //$seat = $request->seat;

        if (!$this->ticketExists($targetTicket)) {

            return response()->json([
                'error' => 'Ticket does not exist'
            ], 404);

        }

        //Find and replace ticket
        $this->tickets = array_map(function($ticket) use ($targetTicket, $action, $value){
            
            if ($ticket['id'] == $targetTicket) {
                $ticket[$action] = $value;
            }

            return $ticket;
           
        }, $this->tickets);

        //Save tickets in JSON file
        $this->saveTickets();

        return response()->json([
            'sucess' => true
        ], 200);

    }

    /**
     * Verify if ticket exists
     * 
     * @param string $ticket
     * 
     * @return bool Whether or not the ticket exists
     */
    private function ticketExists($targetTicket)
    {
        $foundTicket = array_filter($this->tickets, function($ticket) use ($targetTicket){
            return $ticket['id'] == $targetTicket;
        });

        if (!empty($foundTicket)) {
            return true;
        }

        return false;
    }

    /**
     * Create a new ticket
     * 
     * @param Request $request
     * 
     * @return array new ticket
     */
    private function makeTicket(Request $request)
    {
        $ticket = [
            'id'        => uniqid('TICKET'),
            'flight'    => $this->flights[$request->flight], 
            'seat'      => rand(1, 32),
            'passport'  => $request->passport,
            'status'    => 'active'
        ];
 
        return $ticket;
    }

    /**
     * Add a new ticket to the array of tickets
     * 
     * @param string $ticket
     * 
     * @return void
     */
    private function addTicket($ticket)
    {
        $this->tickets[] = $ticket;

        $this->saveTickets();
    }

    private function saveTickets()
    {

        $storageTickets = json_encode($this->tickets);

        Storage::disk('local')->put('tickets.json', $storageTickets);

    }
}

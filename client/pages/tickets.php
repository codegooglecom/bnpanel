<?php
/* For licensing terms, see /license.txt */

//Check if called by script
if(THT != 1){die();}

class page {
	
	public $navtitle;
	public $navlist = array();
							
	public function __construct() {
		$this->navtitle = "Tickets Menu";
		$this->navlist[] = array("New Ticket", "page_white_add.png", "add");
		$this->navlist[] = array("View Tickets", "page_white_go.png", "view");
	}
	
	public function description() {
		return "<strong>Tickets Area</strong><br />
		This is the area where you can add/view tickets that you've created or just created. Any tickets, responses will be sent via email.";	
	}
	
	public function content() { # Displays the page 
		global $main, $style, $db, $email, $ticket;
		
		$user_id = $main->getCurrentUserId();
		switch($main->getvar['sub']) {
			case 'add':
				if($_POST && $main->checkToken()) {
					foreach($main->postvar as $key => $value) {
						if($value == "" && !$n && $key != "admin") {
							$main->errors("Please fill in all the fields!");
							$n++;
						}
					}
					if(!$n) {
						$time = time();
						
						$ticket_params['title']		= $main->postvar['title'];
						$ticket_params['content'] 	= $main->postvar['content'];
						$ticket_params['urgency'] 	= $main->postvar['urgency'];
						$ticket_params['time'] 		= $time;
						$ticket_params['userid'] 	= $user_id;
						$ticket_id = $ticket->create($ticket_params);						
						
						$main->errors("Ticket has been added");
						$template = $db->emailTemplate("new_ticket");
						$array['TITLE'] = $main->postvar['title'];
						$array['URGENCY'] = $main->postvar['urgency'];
						$array['CONTENT'] = $main->postvar['content'];
						$array['ID'] = $ticket_id;
						$email->staff($template['subject'], $template['content'], $array);
						$main->redirect('?page=tickets&sub=view&msg=1');
					}
				}
				echo $style->replaceVar("tpl/support/addticket.tpl", $array);
				break;
			default:
			case 'view':
				if(!$main->getvar['do'] && $main->checkToken()) {
					$query = $db->query("SELECT * FROM <PRE>tickets WHERE userid = '{$user_id}' AND reply = '0'");
					if(!$db->num_rows($query)) {
						$style->showMessage('No open tickets');						
					} else {						
						while($data = $db->fetch_array($query)) {
							$array['TITLE'] = $data['title'];
							$array['UPDATE'] = $ticket->lastUpdated($data['id']);
							$array['ID'] = $data['id'];
							$array['STATUS'] = $data['status'];
							echo $style->replaceVar("tpl/support/ticketviewbox.tpl", $array);
						}
					}
				} else {
					$query = $db->query("SELECT * FROM <PRE>tickets WHERE id = '{$main->getvar['do']}' OR ticketid = '{$main->getvar['do']}' ORDER BY time ASC");
					if(!$db->num_rows($query)) {
						echo "That ticket doesn't exist";	
					} else {
						if($_POST && $main->checkToken()) {
							foreach($main->postvar as $key => $value) {
								if($value == "" && !$n && $key != "admin") {
									$main->errors("Please fill in all the fields");
									$n++;
								}
							}
							if(!$n) {
								$time = time();
								
								$ticket_params['title']		= $main->postvar['title'];
								$ticket_params['content'] 	= $main->postvar['content'];
								$ticket_params['time'] 		= $time;
								$ticket_params['userid'] 	= $user_id;
								$ticket_params['reply'] 	= 1;
								$ticket_params['ticketid'] 	= $main->getvar['do'];
								
								$ticket->create($ticket_params);					
								
								$main->errors("Reply has been added");
								$data = $db->fetch_array($query);
								$client = $db->client($user_id);
								$template = $db->emailTemplate("new_response");
								$array['TITLE'] = $data['title'];
								$array['USER'] = $client['user'];
								$array['CONTENT'] = $main->postvar['content'];
								$email->staff($template['subject'], $template['content'], $array);
								$main->redirect("?page=tickets&sub=view&do=". $main->getvar['do']);
							}
						}
						$data = $db->fetch_array($query);
						$array['AUTHOR'] = $ticket->determineAuthor($data['userid'], $data['staff']);
						$array['TIME'] = strftime("%D", $data['time']);
						$array['NUMREPLIES'] = $db->num_rows($query) - 1;
						$array['UPDATED'] = $ticket->lastUpdated($data['id']);
						$array['ORIG'] = $ticket->showReply($data['id']);
						$array['URGENCY'] = $data['urgency'];
						$array['STATUS'] = $ticket->status($data['status']);
						
						$n = 0;
						$array['REPLIES'] = "";
						while($reply = $db->fetch_array($query)) {
							if(!$n) {
								$array['REPLIES'] .= "<br /><b>Replies</b>";
							}
							$array['REPLIES'] .= $ticket->showReply($reply['id']);
							$n++;
						}
						
						if($data['status'] != 3) {
							$array['ADDREPLY'] .= "<br /><b>Add Reply</b>";
							$array2['TITLE'] = "RE: ". $data['title'];
							$array['ADDREPLY'] .= $style->replaceVar("tpl/support/addreply.tpl", $array2);
						}
						else {
							$array['ADDREPLY'] = "";	
						}						
						$array['ID'] = $data['id'];
						echo $style->replaceVar("tpl/support/viewticket.tpl", $array);	
					}
				}
				break;
		}
	}
}
<?php
	// Create the empty variable that contains the svg output
        $output="";

        // Width and height of the vm box
        $vm_width=200;
        $vm_height=160;

        // Inner vm box size, slightly smaller than overall vm box to allow some padding
        $vm_box_width=180;

        // Get the offset position of position of the vm box
        $vm_box_x_offset=(($vm_width-$vm_box_width)/2);

        // Heights to give ;to the network names
        $network_names_height=50;

        // Width to give to the network names
        $network_names_width=280;

        // x start position of the internal network lines
        $network_lines_start_internal=$network_names_width-20;

        // x start position of the external network lines
        $network_lines_start_external=$network_names_width-30;

        // Finish x position of the network lines
        $network_lines_finish=((sizeof($vms) * $vm_width) + $network_names_width);

        // How far in to start drawing the nics
        $nic_x_offset=15;

        // Width of the nics
        $nic_width=10;

        // Height of the nics
        $nic_height=18;

        // Seperator between the nics
        $nic_seperator=10;

        // Start of the svg element
        $output.="<svg id='svg_element' xmlns='http://www.w3.org/2000/svg' version='1.1'>";
	$output.="<defs>
	<linearGradient id='vmgradient' x1='0%' y1='0%' x2='0%' y2='100%'>
	<stop offset='0%' style='stop-color:rgb(200,200,200);stop-opacity:1' />
	<stop offset='100%' style='stop-color:rgb(220,220,220);stop-opacity:1' />
	</linearGradient>
	</defs>";
        // Counter to keep track of the networks as we loop through them
        $network_counter=0;

        // Lets loop through external networks
        foreach ($vapp_networks_external as $vapp_network_external)
        {
                // Calculate how far down this network should appear
                $network_y=(($network_counter * $network_names_height) + $vm_height);

                // Draw an image of a globe to indicate its public / external
                $output.="<image x='0' y='" . ($network_y - 15) . "' width='16' height='16' xlink:href='/images/staticfiles/earth.png' />";

                // Draw the text with the network name
                $output.="<text class='connection net" . $network_counter . "' x='30' y='" . $network_y . "' fill='#6495ED'>" . $vapp_network_external['name'] . "</text>";

                // Draw the horizontal line for this network
                $output.="<line class='connection net" . $network_counter . "' x1='" . $network_lines_start_external . "' y1='" . $network_y . "' x2='" . $network_lines_finish . "' y2='" . $network_y . "' stroke='#6495ED' stroke-width='3' />";

                // Increment the network counter
                $network_counter++;
        }

        // Lets loop through internal networks
        foreach ($vapp_networks_internal as $vapp_network_internal)
        {
                // Calculate how far down this network should appear
                $network_y=(($network_counter * $network_names_height) + $vm_height);

                // Draw the text with the network name
                $output.="<text class='connection net" . $network_counter . "' x='0' y='" . $network_y . "' fill='grey'>" . $vapp_network_internal['name'] . "</text>";

                // Draw the horizontal line for this network
                $output.="<line class='connection net" . $network_counter . "' x1='" . $network_lines_start_internal . "' y1='" . $network_y . "' x2='" . $network_lines_finish . "' y2='" . $network_y . "' stroke='grey' stroke-width='3' />";

                // Increment the network counter
                $network_counter++;
        }

        // Counter to keep track of vms as we loop through them
        $vm_counter=0;

        // Lets loop through each vm
        foreach ($vms as $vm) {

                // Calculate how far x to start drawing the vm details
                $vm_x=$network_names_width+($vm_counter*$vm_width)+$vm_box_x_offset;

                // The surrounding box for each vm
                $output.="<rect x='" . $vm_x . "' y='30' width='" . $vm_box_width . "' height='50' fill='url(#vmgradient)' stroke='black' stroke-width='1' rx='4' ry='4' />";

                // The name of the vm
                $output.="<text x='" . ($vm_x + ($vm_box_width / 2)) . "' y='50' fill='black' font-weight='bold' text-anchor='middle'>" . $vm['name'] . "</text>";

                // The horizontal line thats shown inside each vm to give it some style
                $output.="<line x1='" . $vm_x . "' y1='68' x2='" . ($vm_x + $vm_box_width) . "' y2='68' stroke='grey' stroke-width='1' />";

                // Counter to keep of the nics on that vm
                $nic_counter=0;

                // Lets loop through each nic
                foreach ($vm['network_details'] as $network_details)
                {
                        // Calculate the x position of the nic
                        $nic_x=($vm_x + $nic_x_offset + ($nic_counter * ($nic_width+$nic_seperator)));

                        // Set the y position of the nic
                        $nic_y=74;

                        // Rectangle signifying the nic
                        $output.="<rect x='" . $nic_x . "' y='" . $nic_y . "' width='" . $nic_width . "' height='" . $nic_height . "' fill='grey' stroke='black' stroke-width='1' />";

                        // Green circle inside the rectangle
                        $output.="<circle cx='" . ($nic_x + ($nic_width / 2)) . "' cy='" . ($nic_y + 12) . "' r='3' fill='#33CC66' stroke='black' stroke-width='1' />";

                        // Opaque clickable rectangle that triggers the dialog box with nic details
                        $output.="<rect class='clickable' x='" . $nic_x . "' y='" . $nic_y . "' width='" . $nic_width . "' height='" . $nic_height . "' style='opacity:0.0;cursor:pointer;' >";


                        // Figure out what network the nic is on, and draw the line from the nic down to the right network
                        $network_counter=0;

                        // Variable controlling when we have found the correct network to connect to
                        $found_network=false;

                        // Variable controlling whether its an internal or external network connection
                        $external_network=false;

                        // Loop through each external network to see if we should connect it
                        foreach ($vapp_networks_external as $vapp_network_external)
                        {
                                if ($vapp_network_external['name']==$network_details['network_name'])
                                {
                                        $found_network=true;
                                        $external_network=true;
                                        break;
                                }
                                $network_counter++;
                        }

                        // Loop through each internal network to see if we should connect it, only if we havn't already found a matching external one
                        if (!$found_network)
                        {
                                foreach ($vapp_networks_internal as $vapp_network_internal)
                                {
                                        if ($vapp_network_internal['name']==$network_details['network_name'])
                                        {
                                                $found_network=true;
                                                break;
                                        }
                                        $network_counter++;
                                }
                        }

			// Tooltip for the rectangle
                        $output.="  <title>Click for more details</title>";

                        // xml containing details about the nic, thats used by the dialog box
                        $output.="  <nic_no>" . $network_details['nic_no'] . "</nic_no>";
                        $output.="  <mac_addr>" . $network_details['macaddress'] . "</mac_addr>";
                        $output.="  <network_name>" . $network_details['network_name'] . "</network_name>";

			// Only show the ip if its a public network
			if ($external_network)
			{
				// Only show the ip if its a public network and is known
	                        if ($network_details['ipaddress']!="")
	                        {
					$output.="  <ip_address>" . $network_details['ipaddress'] . "</ip_address>";
	                        }
			}
			// End of the nic rectangle
			$output.="</rect>";

                        // If we found a match, draw the connecting line
                        if ($found_network)
                        {

                                // Use a different color if its an external network
                                if ($external_network)
                                {
                                        $line_color="#6495ED";
                                }
                                else
                                {
                                        $line_color="grey";
                                }

                                // Calculate how far down to draw the line, based on the network counter
                                $network_y=(($network_counter * $network_names_height) + $vm_height);

                                // Draw the connecting line
                                $output.="<line class='connection net" . $network_counter . "' x1='" . ($nic_x + ($nic_width / 2)) . "' y1='91' x2='" . ($nic_x + ($nic_width / 2)) . "' y2='" . $network_y . "' stroke='" . $line_color . "' stroke-width='3' />";

                                // Draw the connecting dot
                                $output.="<circle class='connection net" . $network_counter . "' cx='" . ($nic_x + ($nic_width / 2)) . "' cy='" . $network_y . "' r='5' fill='black' />";
                        }

                        // Increment the nic counter
                        $nic_counter++;
                }

                // Increment the vm counter
                $vm_counter++;

        }

        // Finish the svg element
        $output.="</svg>";
	echo $output;
?>

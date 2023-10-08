<?php
/**
* Plugin Name: WP-CLI SSH Plugin Fetcher with Credentials in Options
* Plugin URI: https://github.com/vincenzo85/ssh-fetcher.git
* Description: Add WP-CLI comannd for compare plugin list
* Version: 1.0.1
* Author: Vincenzo Di Franco
*/

if (defined('WP_CLI') && WP_CLI) {

    class SSH_Plugin_Fetcher_WP_CLI
    {

        private $option_key = 'ssh_plugin_fetcher_credentials';
        private $option_remote = 'ssh_plugin_remote';
        private $option_locale = 'ssh_plugin_locale';

        public function connect($arg, $ass_arg): void
        {
            $ass_arg = $this->use_saved_credentials($ass_arg);
            $command = $this->ssh_command($ass_arg);
            $this->update_staging_list($ass_arg);
            $output = $this->send_command($command);
            $this->save_remove_list($output);
            $this->save_credentials($ass_arg);
        }

        /**
         * Checks for the 'use_saved' key in the $ass_arg array.
         * If present, use saved options as credential
         * @param array $ass_arg
         * @return array
         */
        private function use_saved_credentials(array $ass_arg):array{
            if (isset($ass_arg['use_saved'])){
                $ass_arg = get_option($this->option_key);
                $pass = $ass_arg['password'];
                $ass_arg['password'] = '******';
                WP_CLI::line(print_r($ass_arg, true));
                $ass_arg['password'] = $pass;
            }
            return $ass_arg;
        }

        /**
         * designed to generate and display an SSH command
         * for listing WordPress plugins in a specific format.
         * @param $ass_arg
         * @return string
         */
        private function ssh_command($ass_arg){
            $command = 'false';
            if ($this -> check_argument($ass_arg)){
                $command = sprintf (
                    'sshpass -p \'%s\' ssh %s@%s -p %s "cd %s && wp plugin list --format=json " ',
                    $ass_arg['password'], $ass_arg['username'],
                    $ass_arg['host'], $ass_arg['port'], $ass_arg['pathserver']);
                if(isset($ass_arg['noshhpass']))
                $command = sprintf (
                'ssh %s@%s -p %s "cd %s && wp plugin list --format=json " ',
                $ass_arg['username'],
                $ass_arg['host'], $ass_arg['port'], $ass_arg['pathserver']);
         
                // show command without password
                $command_show = sprintf (
                    'sshpass -p \'%s\' ssh %s@%s -p %s "cd %s && wp plugin list --format=json " ',
                    '*********', $ass_arg['username'], $ass_arg['host'],
                    $ass_arg['port'], $ass_arg['pathserver']);

                WP_CLI::line($command_show);
            }
            return $command;

        }


         /**
         * Save remote plugin list in options
         * Command ex. wp ssh_fetcher save_credentials 
         * @return void
         */
        public function save_credentials(){
            $user_name = $this -> get_username();
            $host = $this -> get_host();
            $port = $this -> get_port();
            $pathserver = $this -> get_path();
            $sshpass = $this -> get_sshpass();
            $pass = $this -> pass;
        
           $credentials = array(
                'username' => $user_name,
                'host' => $host,
                'port' => $port,
                'password'=> '*************',
                'sshpass' => $sshpass,
                'pathserver' => $pathserver,
            );

            WP_CLI::line('Saving this credential to options '.print_r(json_encode($credentials), true) );

            $credentials['password'] = $pass;

            $result = update_option($this->option_remote, $credentials);

            if ($result)
              WP_CLI::line('Credential Saved successfull');
        }

          private function get_username(){
            WP_CLI::line('Please enter username: ');
            ob_flush(); 
            return trim(fgets(STDIN));    
        }

          private function get_host(){
            WP_CLI::line('Please enter host: ');
            ob_flush(); 
            return trim(fgets(STDIN));
          }

          private function get_port(){
             WP_CLI::line('Please enter port: ');
            ob_flush(); 
            return trim(fgets(STDIN));
          }

          private function get_path(){
            WP_CLI::line('Set valid path of root WordPress in server: ');
            ob_flush(); 
            return trim(fgets(STDIN));
          }
          
          private function get_sshpass(){  
           $validResponse = false;
            $pass = '';
            $this -> $pass = $pass;
            do {
                WP_CLI::line('Did you have sshpass installed in your system? Y/N: ');
                ob_flush(); 
                $response = strtoupper(trim(fgets(STDIN))); // Convert to uppercase for case-insensitive comparison
        
                if ($response == "Y") {
                    $validResponse = true;
                    $sshpass = 'Y'; // or whatever value you want to assign
                    WP_CLI::line('Please enter password: ');
                    ob_flush(); 
                    $pass = trim(fgets(STDIN));
                    $this -> $pass = $pass;
                  
                } elseif ($response == "N") {
                    WP_CLI::line('System will ask You for password everytime you fetch plugins ');                   
                    $validResponse = true;
                    $sshpass = 'N';
                } else {
                    WP_CLI::line('Invalid response. Please answer with Y or N.');
                }
            } while (!$validResponse);

            return $sshpass;
          }

        private function check_argument($ass_arg) {
            // Definisci la lista delle chiavi obbligatorie
            $requiredKeys = array('password', 'username', 'host', 'port', 'pathserver');
            $message = array(
                'password' => 'server password is required',
                'username' => 'server username is required',
                'host' => 'server host is required',
                'port' => 'server port is required',
                'path' => 'server path is required'
            );
            // Controlla se ogni chiave obbligatoria esiste nell'array multidimensionale
            foreach ($requiredKeys as $key) {
                if ($key == 'password')
                WP_CLI::line($key.' key check '. print_r($ass_arg, true));
                    
                if ($key == 'password' && isset($ass_arg['nosshpass']) ){
                    return true;
                }
                if (!array_key_exists($key, $ass_arg)) {
                    WP_CLI::line($key.' not found');
                    return false;// Chiave obbligatoria mancante
                }
                
            }

            // Se il ciclo si completa senza ritornare, tutte le chiavi obbligatorie sono presenti
            return true;
        }

        /**
         * checks if the 'updatelist' key is present
         * retrieves the current list of WordPress plugins
         * in JSON format using WP-CLI and then updates
         * option
         * example command : wp ssh-fetcher --updatelist
         * @param $ass_arg
         * @return void
         */
        private function update_staging_list($ass_arg){
            if (isset($ass_arg['updatelist'])) {  // create a list of plugins in staging
                $staging_list = WP_CLI::runcommand('plugin list --format=json', array('return' => 'stdout'));
                update_option($this->option_remote, $staging_list);
            }
        }

        /**
         * Send command to remote environment
         * @param $command
         * @return false|string|null
         */
        private function send_command($command){
            $output = 'no command sent';
            if($command != 'false'){
                $output = shell_exec($command);
            }
            WP_CLI::line($output);
            if($command != 'false'){
                $output = 'false';
            }
            return $output;
        }

        /**
         * Save remote plugin list in options
         * @param $output
         * @return void
         */
        private function save_remove_list($output){
            if ($output != 'false')
                if (isset($ass_arg['updatelist']))
                    update_option($this->option_remote, $output);
        }

        /**
         * Save credential in options
         * @param $ass_arg
         * @return void
         */
        private function save_credentials_($ass_arg){
            if (isset($ass_arg['updatecredential'])){
                $credentials['password'] = $ass_arg['password'];
                $credentials['username'] = $ass_arg['username'];
                $credentials['host'] = $ass_arg['host'];
                $credentials['port'] = $ass_arg['port'];
                $credentials['pathserver'] = $ass_arg['pathserver'];
                $result = update_option($this->option_key, $credentials);
                if ($result)
                    WP_CLI::line('credential saved');
            }

        }

    }

    WP_CLI::add_command('ssh-fetcher', 'SSH_Plugin_Fetcher_WP_CLI');
}
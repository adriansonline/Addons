<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['CivilTongueEx'] = array(
   'Name' => 'Civil Tongue Ex',
   'Description' => 'A swear word filter for your forum. Making your forum safer for younger audiences. This version of the plugin is based on the Civil Tongue plugin.',
   'Version' => '1.0.3',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => array('Vanilla' => '2.1'),
   'Author' => "Todd Burry",
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.org/profile/todd',
   'SettingsUrl' => '/dashboard/plugin/tongue',
	'SettingsPermission' => 'Garden.Settings.Manage'
);

// 1.0 - Fix empty pattern when list ends in semi-colon, use non-custom permission (2012-03-12 Lincoln)

class CivilTonguePlugin extends Gdn_Plugin {
   public $Replacement;

   public function  __construct() {
      parent::__construct();
      $this->Replacement = C('Plugins.CivilTongue.Replacement', '');
   }
   
   /**
    * Add settings page to Dashboard sidebar menu.
    */
	public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Forum', T('Censored Words'), 'plugin/tongue', 'Garden.Settings.Manage');
   }
   
   public function Base_FilterContent_Handler($Sender, $Args) {
      if (!isset($Args['String']))
         return;
      
      $Args['String'] = $this->Replace($Args['String']);
   }

	public function PluginController_Tongue_Create($Sender, $Args = array()) {
		$Sender->Permission('Garden.Settings.Manage');
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
		$ConfigurationModel->SetField(array('Plugins.CivilTongue.Words', 'Plugins.CivilTongue.Replacement'));
		$Sender->Form->SetModel($ConfigurationModel);
		
		if ($Sender->Form->AuthenticatedPostBack() === FALSE) { 
			
         $Sender->Form->SetData($ConfigurationModel->Data);    
      } else {
         $Data = $Sender->Form->FormValues();
			
         if ($Sender->Form->Save() !== FALSE)
            $Sender->StatusMessage = T("Your settings have been saved.");
      }

		$Sender->AddSideMenu('plugin/tongue');		
		$Sender->SetData('Title', T('Civil Tongue'));
		$Sender->Render($this->GetView('index.php'));
      
	}

   public function ProfileController_Render_Before($Sender, $Args) {
      $this->ActivityController_Render_Before($Sender, $Args);
   }

   /**
    * Clean up activities and activity comments.
    * 
    * @param Controller $Sender
    * @param array $Args
    */
   public function ActivityController_Render_Before($Sender, $Args) {
      $User = GetValue('User', $Sender);
      if ($User)
         SetValue('About', $User, $this->Replace(GetValue('About', $User)));

      if (isset($Sender->Data['Activities'])) {
         $Activities =& $Sender->Data['Activities'];
         foreach ($Activities as &$Row) {
            SetValue('Story', $Row, $this->Replace(GetValue('Story', $Row)));

            if (isset($Row['Comments'])) {
               foreach ($Row['Comments'] as &$Comment) {
                  $Comment['Body'] = $this->Replace($Comment['Body']);
               }
            }
         }
      }

      $CommentData = GetValue('CommentData', $Sender);
      if ($CommentData) {
         $Result =& $CommentData->Result();
         foreach ($Result as &$Row) {
            $Value = $this->Replace(GetValue('Story', $Row));
            SetValue('Story', $Row, $Value);

            $Value = $this->Replace(GetValue('DiscussionName', $Row));
            SetValue('DiscussionName', $Row, $Value);

            $Value = $this->Replace(GetValue('Body', $Row));
            SetValue('Body', $Row, $Value);

         }
      }
   }

   /**
    * Clean up the last title.
    *
    * @param CategoriesController $Sender
    */
   public function CategoriesController_Render_Before($Sender) {
      if (isset($Sender->Data['Categories'])) {
         foreach ($Sender->Data['Categories'] as &$Row) {
            if (is_array($Row)) {
               if (isset($Row['LastTitle'])) {
                  $Row['LastTitle'] = $this->Replace($Row['LastTitle']);
               }
            } elseif (is_object($Row)) {
               if (isset($Row->LastTitle)) {
                  $Row->LastTitle = $this->Replace($Row->LastTitle);
               }
            }
         }
      }
   }

   /**
    * Censor words in discussions / comments.
    */
   public function DiscussionController_Render_Before($Sender, $Args) {
      // Process OP
      $Discussion = GetValue('Discussion', $Sender);
      if ($Discussion) {
         $Discussion->Name = $this->Replace($Discussion->Name);
         if (isset($Discussion->Body)) {
            $Discussion->Body = $this->Replace($Discussion->Body);
         }
      }
      
      // Get comments (2.1+)
      $Comments = $Sender->Data('Comments');
      
      // Backwards compatibility to 2.0.18
      if (isset($Sender->CommentData)) 
         $Comments = $Sender->CommentData->Result();
      
      // Process comments
      if ($Comments) {
         foreach ($Comments as $Comment) {
            $Comment->Body = $this->Replace($Comment->Body);
         }
      }
   }

   /**
    * Clean up the search results.
    * @param SearchController $Sender
    */
   public function SearchController_Render_Before($Sender) {
      if (isset($Sender->Data['SearchResults'])) {
         $Results =& $Sender->Data['SearchResults'];
         foreach ($Results as &$Row) {
            $Row['Title'] = $this->Replace($Row['Title']);
            $Row['Summary'] = $this->Replace($Row['Summary']);
         }
      }
   }

   public function Base_BeforeDiscussionName_Handler($Sender, $Args) {
      $Discussion = GetValue('Discussion', $Args);
      if ($Discussion) {
         $Discussion->Name = $this->Replace($Discussion->Name);
         if (isset($Discussion->Body)) {
            $Discussion->Body = $this->Replace($Discussion->Body);
         }
      }
   }

   /**
    * Clean up the search results.
    * @param RootController $Sender
    */
   public function RootController_BestOf_Render($Sender) {

      if (isset($Sender->Data['Data'])) {
         foreach ($Sender->Data['Data'] as &$Row) {
            $Row['Name'] = $this->Replace($Row['Name']);
            $Row['Body'] = $this->Replace($Row['Body']);
         }
      }
   }
   
   public function UtilityController_CivilPatterns_Create($Sender) {
      $Patterns = $this->GetPatterns();

      $Text = "What's a person to do? ass";
      $Result = array();
      
      foreach ($Patterns as $Pattern) {
         $r = preg_replace($Pattern, $this->Replace, $Text);
         if ($r != $Text)
            $Result[] = $Pattern;
      }
      
      $Sender->SetData('Matches', $Result);
      $Sender->SetData('Patterns', $Patterns);
      $Sender->Render('Blank', 'Utility');
   }

   public function Replace($Text) {
      $Patterns = $this->GetPatterns();
      $Result = preg_replace($Patterns, $this->Replacement, $Text);
//      $Result = preg_replace_callback($Patterns, function($m) { return $m[0][0].str_repeat('*', strlen($m[0]) - 1); }, $Text);
      return $Result;
   }
	
	public function GetPatterns() {
		// Get config.
		static $Patterns = NULL;

      if ($Patterns === NULL) {
         $Patterns = array();
         $Words = C('Plugins.CivilTongue.Words', null);
         if($Words !== null) {
            $ExplodedWords = explode(';', $Words);
            foreach($ExplodedWords as $Word) {
               if (trim($Word))
                  $Patterns[] = '`\b' . preg_quote(trim($Word), '`') . '\b`is';
            }
         }
      }
		return $Patterns;
	}
	
	
   public function Setup() {
      // Set default configuration
		SaveToConfig('Plugins.CivilTongue.Replacement', '****');
   }
}

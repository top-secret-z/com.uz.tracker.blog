<?php
namespace blog\system\event\listener;
use blog\data\blog\Blog;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to Blog action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.blog
 */
class TrackerBlogListener implements IParameterizedEventListener {
	/**
	 * tracker and link
	 */
	protected $tracker = null;
	protected $link = '';
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MODULE_TRACKER) return;
		
		// only if user is to be tracked
		$user = WCF::getUser();
		if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) return;
		
		// only if trackers
		$trackers = TrackerCacheBuilder::getInstance()->getData();
		if (!isset($trackers[$user->userID])) return;
		
		$this->tracker = $trackers[$user->userID];
		if (!$this->tracker->wlbloBlog && !$this->tracker->otherModeration) return;
		
		// blog actions
		$action = $eventObj->getActionName();
		
		if ($action == 'create') {
			$returnValues = $eventObj->getReturnValues();
			$blog = $returnValues['returnValues'];
			$this->link = $blog->getLink();
			
			$this->store('wcf.uztracker.description.blog.add', 'wcf.uztracker.type.wlblo');
		}
		
		if ($action == 'delete') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $blog) {
				$this->link = '';
				$name = $blog->title;
				
				if ($blog->userID == $user->userID) {
					if ($this->tracker->wlbloBlog) $this->store('wcf.uztracker.description.blog.delete', 'wcf.uztracker.type.wlblo', $name);
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.blog.delete', 'wcf.uztracker.type.moderation', $name);
				}
			}
		}
		
		if ($action == 'update') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $blog) {
				$this->link = $blog->getLink();
				if ($blog->userID == $user->userID) {
					if ($this->tracker->wlbloBlog) $this->store('wcf.uztracker.description.blog.update', 'wcf.uztracker.type.wlblo');
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.blog.update', 'wcf.uztracker.type.moderation');
				}
			}
		}
		
		if ($this->tracker->otherModeration) {
			if ($action == 'setAsFeatured' || $action == 'unsetAsFeatured') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $blog) {
					$this->link = $blog->getLink();
					if ($action == 'setAsFeatured') $this->store('wcf.uztracker.description.blog.setAsFeatured', 'wcf.uztracker.type.moderation');
					else $this->store('wcf.uztracker.description.blog.unsetAsFeatured', 'wcf.uztracker.type.moderation');
				}
			}
		}
	}
	
	/**
	 * store log entry
	 */
	protected function store ($description, $type, $name = '') {
		$packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.blog');
		TrackerLogEditor::create(array(
				'description' => $description,
				'link' => $this->link,
				'name' => $name,
				'trackerID' => $this->tracker->trackerID,
				'type' => $type,
				'packageID' => $packageID
		));
	}
}

<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace blog\system\event\listener;

use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to Blog Entry action.
 */
class TrackerBlogEntryListener implements IParameterizedEventListener
{
    /**
     * tracker and link
     */
    protected $tracker;

    protected $link = '';

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (!MODULE_TRACKER) {
            return;
        }

        // only if user is to be tracked
        $user = WCF::getUser();
        if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) {
            return;
        }

        // only if trackers
        $trackers = TrackerCacheBuilder::getInstance()->getData();
        if (!isset($trackers[$user->userID])) {
            return;
        }

        $this->tracker = $trackers[$user->userID];
        if (!$this->tracker->wlbloEntry && !$this->tracker->otherModeration) {
            return;
        }

        // actions / data
        $action = $eventObj->getActionName();

        if ($this->tracker->wlbloEntry) {
            if ($action == 'create') {
                $returnValues = $eventObj->getReturnValues();
                $entry = $returnValues['returnValues'];
                $this->link = $entry->getLink();

                if ($entry->isDraft) {
                    $this->store('wcf.uztracker.description.blog.entry.addDraft', 'wcf.uztracker.type.wlblo');
                } elseif ($entry->isDisabled) {
                    $this->store('wcf.uztracker.description.blog.entry.addDisabled', 'wcf.uztracker.type.wlblo');
                } elseif (!$entry->isPublished) {
                    $this->store('wcf.uztracker.description.blog.entry.addLater', 'wcf.uztracker.type.wlblo');
                } else {
                    $this->store('wcf.uztracker.description.blog.entry.add', 'wcf.uztracker.type.wlblo');
                }
            }
        }

        if ($this->tracker->otherModeration) {
            if ($action == 'disable' || $action == 'enable') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $entry) {
                    $this->link = $entry->getLink();
                    if ($action == 'disable') {
                        $this->store('wcf.uztracker.description.blog.entry.disable', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->store('wcf.uztracker.description.blog.entry.enable', 'wcf.uztracker.type.moderation');
                    }
                }
            }

            if ($action == 'delete') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $entry) {
                    $this->link = '';
                    $name = $entry->subject;
                    $content = $entry->message;
                    $this->store('wcf.uztracker.description.blog.entry.delete', 'wcf.uztracker.type.moderation', $name, $content);
                }
            }

            if ($action == 'setAsFeatured' || $action == 'unsetAsFeatured') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $entry) {
                    $this->link = $entry->getLink();
                    if ($action == 'setAsFeatured') {
                        $this->store('wcf.uztracker.description.blog.entry.setAsFeatured', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->store('wcf.uztracker.description.blog.entry.unsetAsFeatured', 'wcf.uztracker.type.moderation');
                    }
                }
            }
        }

        if ($action == 'trash' || $action == 'restore') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $entry) {
                $this->link = $entry->getLink();
                if ($action == 'trash') {
                    if ($entry->userID == $user->userID) {
                        if ($this->tracker->wlbloEntry) {
                            $this->store('wcf.uztracker.description.blog.entry.trash', 'wcf.uztracker.type.wlblo');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.blog.entry.trash', 'wcf.uztracker.type.moderation');
                        }
                    }
                } else {
                    if ($entry->userID == $user->userID) {
                        if ($this->tracker->wlbloEntry) {
                            $this->store('wcf.uztracker.description.blog.entry.restore', 'wcf.uztracker.type.wlblo');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.blog.entry.restore', 'wcf.uztracker.type.moderation');
                        }
                    }
                }
            }
        }

        if ($action == 'update') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $entry) {
                $this->link = $entry->getLink();
                if ($entry->userID == $user->userID) {
                    if ($this->tracker->wlbloEntry) {
                        $this->store('wcf.uztracker.description.blog.entry.update', 'wcf.uztracker.type.wlblo');
                    }
                } else {
                    if ($this->tracker->otherModeration) {
                        $this->store('wcf.uztracker.description.blog.entry.update', 'wcf.uztracker.type.moderation');
                    }
                }
            }
        }
    }

    /**
     * store log entry
     */
    protected function store($description, $type, $name = '', $content = '')
    {
        $packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.blog');
        TrackerLogEditor::create([
            'description' => $description,
            'link' => $this->link,
            'name' => $name,
            'trackerID' => $this->tracker->trackerID,
            'type' => $type,
            'packageID' => $packageID,
            'content' => $content,
        ]);
    }
}

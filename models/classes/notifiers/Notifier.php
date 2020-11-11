<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */
namespace oat\tao\model\notifiers;

/**
 * Sends a notification to an external service
 *
 * Interface Notifier
 * @author Andrey Niahrou <Andrei.Niahrou@1pt.com>
 * @package oat\tao\model\notifiers
 */
interface Notifier
{
    /**
     * @param string $title
     * @param string $description
     * @param array $parameters
     * @return array
     */
    public function notify(string $title, string $description, array $parameters = []): array;
}
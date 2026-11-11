<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

final class EventType extends AbstractType
{
    public static function handle(): string
    {
        return 'Event';
    }

    public static function label(): string
    {
        return 'Event';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'kind' => 'text', 'required' => true, 'default' => 'name'],
            ['name' => 'description', 'label' => 'Description', 'kind' => 'textarea', 'default' => 'description'],
            ['name' => 'startDate', 'label' => 'Starts', 'kind' => 'datetime', 'required' => true],
            ['name' => 'endDate', 'label' => 'Ends', 'kind' => 'datetime'],
            ['name' => 'status', 'label' => 'Status', 'kind' => 'select', 'options' => [
                'EventScheduled' => 'Scheduled', 'EventCancelled' => 'Cancelled', 'EventPostponed' => 'Postponed', 'EventMovedOnline' => 'Moved online',
            ]],
            ['name' => 'attendance', 'label' => 'Attendance', 'kind' => 'select', 'options' => [
                'OfflineEventAttendanceMode' => 'In person', 'OnlineEventAttendanceMode' => 'Online', 'MixedEventAttendanceMode' => 'In person and online',
            ]],
            ['name' => 'locationName', 'label' => 'Venue name', 'kind' => 'text', 'help' => 'Google needs a venue for in-person events.'],
            ['name' => 'street', 'label' => 'Street', 'kind' => 'text'],
            ['name' => 'locality', 'label' => 'Town / city', 'kind' => 'text'],
            ['name' => 'postcode', 'label' => 'Postcode', 'kind' => 'text'],
            ['name' => 'country', 'label' => 'Country', 'kind' => 'text'],
            ['name' => 'onlineUrl', 'label' => 'Online event URL', 'kind' => 'url'],
            ['name' => 'image', 'label' => 'Image URL', 'kind' => 'url'],
            ['name' => 'price', 'label' => 'Ticket price', 'kind' => 'number'],
            ['name' => 'currency', 'label' => 'Currency', 'kind' => 'select', 'options' => self::currencies()],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        $attendance = $data['attendance'] ?: 'OfflineEventAttendanceMode';
        $locations = [];

        if ($attendance !== 'OnlineEventAttendanceMode' && ($data['locationName'] !== '' || $data['street'] !== '')) {
            $locations[] = SchemaBuilder::clean([
                '@type' => 'Place',
                'name' => $data['locationName'],
                'address' => self::address([
                    'streetAddress' => $data['street'],
                    'addressLocality' => $data['locality'],
                    'postalCode' => $data['postcode'],
                    'addressCountry' => $data['country'],
                ]),
            ]);
        }

        if ($attendance !== 'OfflineEventAttendanceMode' && $data['onlineUrl'] !== '') {
            $locations[] = ['@type' => 'VirtualLocation', 'url' => $data['onlineUrl']];
        }

        return SchemaBuilder::clean([
            '@type' => 'Event',
            '@id' => $page['url'] . '#event',
            'url' => $page['url'],
            'name' => $data['name'],
            'description' => $data['description'],
            'startDate' => self::withOffset($data['startDate'], $site),
            'endDate' => self::withOffset($data['endDate'], $site),
            'eventStatus' => 'https://schema.org/' . ($data['status'] ?: 'EventScheduled'),
            'eventAttendanceMode' => 'https://schema.org/' . $attendance,
            'location' => count($locations) === 1 ? $locations[0] : $locations,
            'image' => $data['image'],
            'offers' => self::offer($data['price'], $data['currency'] ?: 'GBP', $page['url']),
            'organizer' => self::organization($site),
        ]);
    }
}

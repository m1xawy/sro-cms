<?php

return [

    /*
    |--------------------------------------------------------------------------
    | In-Game Ranking
    |--------------------------------------------------------------------------
    */
    'ranking' => [
        'custom' => [
            'player' => [
                'enabled' => true,
                'name' => 'Player',
                'query' => "SELECT TOP(20)
                                _Char.CharID,
                                _Char.CharName16 AS Character,
                                _Char.RefObjID,

                                ISNULL((sum(ISNULL(_BindingOptionWithItem.nOptValue, 0)) +
                                sum(ISNULL(_Items.OptLevel, 0)) +
                                sum(ISNULL(_RefObjCommon.ReqLevel1, 0)) +
                                sum(ISNULL(CASE WHEN _RefObjCommon.CodeName128 LIKE '%_A_RARE%' THEN 5 ELSE 0 END, 0)) +
                                sum(ISNULL(CASE WHEN _RefObjCommon.CodeName128 LIKE '%_B_RARE%' THEN 10 ELSE 0 END, 0)) +
                                sum(ISNULL(CASE WHEN _RefObjCommon.CodeName128 LIKE '%_C_RARE%' THEN 15 ELSE 0 END, 0))), 0) as Point

                            FROM
                                _Char WITH (NOLOCK)
                                JOIN _Inventory WITH (NOLOCK) ON _Inventory.CharID = _Char.CharID
                                JOIN _Items WITH (NOLOCK) ON _Items.ID64 = _Inventory.ItemID
                                JOIN _RefObjCommon WITH (NOLOCK) ON _RefObjCommon.ID = _Items.RefItemID
                                LEFT JOIN _BindingOptionWithItem WITH (NOLOCK) ON _BindingOptionWithItem.nItemDBID = _Items.ID64 AND _BindingOptionWithItem.nOptValue > 0 AND _BindingOptionWithItem.bOptType = 2

                            WHERE
                                _Inventory.Slot < 13 AND
                                _Inventory.Slot != 8 AND
                                _Inventory.Slot != 7 AND
                                _Inventory.ItemID > 0 AND
                                _Char.deleted = 0 AND
                                _Char.CharID > 0 AND
                                _Char.CharName16 NOT IN ('[GM]Eva', '[GM]m1xawy')

                            GROUP BY
                                _Char.CharID,
                                _Char.CharName16,
                                _Char.RefObjID

                            ORDER BY
                                Point DESC",
            ],
            'guild' => [
                'enabled' => true,
                'name' => 'Guild',
                'query' => "SELECT TOP(20)
                                 _Guild.ID,
                                 _Guild.Name AS Guild,

                                ISNULL((sum(ISNULL(_BindingOptionWithItem.nOptValue, 0)) +
                                sum(ISNULL(_Items.OptLevel, 0)) +
                                sum(ISNULL(_RefObjCommon.ReqLevel1, 0)) +
                                sum(ISNULL(CASE WHEN _RefObjCommon.CodeName128 LIKE '%_A_RARE%' THEN 5 ELSE 0 END, 0)) +
                                sum(ISNULL(CASE WHEN _RefObjCommon.CodeName128 LIKE '%_B_RARE%' THEN 10 ELSE 0 END, 0)) +
                                sum(ISNULL(CASE WHEN _RefObjCommon.CodeName128 LIKE '%_C_RARE%' THEN 15 ELSE 0 END, 0))), 0) as Point

                            FROM
                                _Guild WITH (NOLOCK)
                                JOIN _GuildMember WITH (NOLOCK) ON _GuildMember.GuildID = _Guild.ID
                                JOIN _Inventory WITH (NOLOCK) ON _Inventory.CharID = _GuildMember.CharID
                                JOIN _Items WITH (NOLOCK) ON _Items.ID64 = _Inventory.ItemID
                                JOIN _RefObjCommon WITH (NOLOCK) ON _RefObjCommon.ID = _Items.RefItemID
                                LEFT JOIN _BindingOptionWithItem WITH (NOLOCK) ON _BindingOptionWithItem.nItemDBID = _Items.ID64 AND _BindingOptionWithItem.nOptValue > 0 AND _BindingOptionWithItem.bOptType = 2

                            WHERE
                                _Inventory.Slot < 13 AND
                                _Inventory.Slot != 8 AND
                                _Inventory.Slot != 7 AND
                                _Inventory.ItemID > 0 AND
                                _Guild.Name NOT IN ('RigidStaff', 'Rigid')

                            GROUP BY
                                _Guild.ID,
                                _Guild.Name

                            ORDER BY
                                Point DESC",
            ],
            'unique' => [
                'enabled' => true,
                'name' => 'Unique',
                'query' => "SELECT TOP(20)
                                _Char.CharID,
                                _Char.CharName16 AS Character,
                                _Char.RefObjID,

                                SUM(
                                    CASE
                                        WHEN _CharUniqueKill.MobID = 1954 THEN 1
                                        WHEN _CharUniqueKill.MobID = 1982 THEN 2
                                        WHEN _CharUniqueKill.MobID = 2002 THEN 3
                                        WHEN _CharUniqueKill.MobID = 3810 THEN 4
                                        WHEN _CharUniqueKill.MobID = 3875 THEN 5
                                        WHEN _CharUniqueKill.MobID = 14778 THEN 2
                                        WHEN _CharUniqueKill.MobID = 5871 THEN 1
                                        WHEN _CharUniqueKill.MobID = 3877 THEN 15
                                        WHEN _CharUniqueKill.MobID = 14839 THEN 10
                                        ELSE 0
                                    END
                                ) AS Point

                            FROM _CharUniqueKill WITH (NOLOCK)
                            JOIN _Char WITH (NOLOCK) ON _Char.CharID = _CharUniqueKill.CharID

                            WHERE
                                _CharUniqueKill.MobID IN (1954, 1982, 2002, 3810, 3875, 14778, 5871, 3877, 14839)
                                AND _Char.CharName16 NOT LIKE '[GM]%'
                                AND _Char.deleted = 0
                                AND _Char.CharID > 0

                            GROUP BY
                                _Char.CharID,
                                _Char.CharName16,
                                _Char.RefObjID

                            ORDER BY
                                Point DESC",
            ],
            'level' => [
                'enabled' => true,
                'name' => 'Level',
                'query' => "SELECT TOP(20)
                                _Char.CharID,
                                _Char.CharName16 AS Character,
                                _Char.RefObjID,
                                _Char.CurLevel AS Level

                            FROM
                                SILKROAD_R_SHARD.._Char WITH (NOLOCK)

                            WHERE
                                _Char.deleted = 0 AND
                                _Char.CharID > 0 AND
                                _Char.CharName16 NOT IN ('[GM]Eva', '[GM]Eve', '[GM]Huracan', '[GM]GD', '[GM]m1xawy')

                            ORDER BY
                                _Char.CurLevel DESC,
                                _Char.ExpOffset DESC",
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Battle Pass
    |--------------------------------------------------------------------------
    */
    'battlepass' => [
        // Master toggle — when disabled all battle pass routes are blocked
        'enabled' => false,
        // Premium Pass configuration (editable from Admin Panel later)
        'premium_enabled' => false,
        'premium_price' => 500,
        'points_per_purchase' => 1,
        'silk_per_point' => 1,
        // Silk type used to buy/upgrade: null = auto (vSRO: 0 = silk_own, iSRO: 3 = PremiumSilk)
        // iSRO options: 3 = PremiumSilk, 1 = Silk | vSRO options: 0 = silk_own, 1 = silk_gift, 2 = silk_point
        'silk_type' => null,

        // Battle Pass tiers (level = position in array, id must be unique)
        'tiers' => [
            ['id' => 1,  'points' => 10,    'free_item' => ['id' => 4,     'qty' => 10], 'vip_item' => ['id' => 11,    'qty' => 10]],
            ['id' => 2,  'points' => 25,    'free_item' => ['id' => 5,     'qty' => 10], 'vip_item' => ['id' => 12,    'qty' => 10]],
            ['id' => 3,  'points' => 50,    'free_item' => ['id' => 6,     'qty' => 10], 'vip_item' => ['id' => 13,    'qty' => 10]],
            ['id' => 4,  'points' => 100,   'free_item' => ['id' => 7,     'qty' => 10], 'vip_item' => ['id' => 14,    'qty' => 10]],
            ['id' => 5,  'points' => 150,   'free_item' => ['id' => 8,     'qty' => 10], 'vip_item' => ['id' => 15,    'qty' => 10]],
            ['id' => 6,  'points' => 200,   'free_item' => ['id' => 9,     'qty' => 10], 'vip_item' => ['id' => 16,    'qty' => 10]],
            ['id' => 7,  'points' => 300,   'free_item' => ['id' => 71,    'qty' => 1],  'vip_item' => ['id' => 74,    'qty' => 1]],
            ['id' => 8,  'points' => 500,   'free_item' => ['id' => 12928, 'qty' => 1],  'vip_item' => ['id' => 12939, 'qty' => 1]],
            ['id' => 9,  'points' => 1000,  'free_item' => ['id' => 34252, 'qty' => 1],  'vip_item' => ['id' => 34259, 'qty' => 1]],
            ['id' => 10, 'points' => 1500,  'free_item' => ['id' => 25187, 'qty' => 1],  'vip_item' => ['id' => 25194, 'qty' => 1]],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lucky Wheel
    |--------------------------------------------------------------------------
    */
    'luckywheel' => [
        // Master toggle — when disabled all battle pass routes are blocked
        'enabled' => false,
        'spin-cost' => 20,
        'max_per_buy' => 10,
        // Silk type used to buy/upgrade: null = auto (vSRO: 0 = silk_own, iSRO: 3 = PremiumSilk)
        // iSRO options: 3 = PremiumSilk, 1 = Silk | vSRO options: 0 = silk_own, 1 = silk_gift, 2 = silk_point
        'silk_type' => null,

        // Lucky Wheel Prizes items
        'prizes' => [
            ['id' => 1, 'lucky' => 10, 'item_id' => 4, 'qty' => 10],
            ['id' => 2, 'lucky' => 10, 'item_id' => 5, 'qty' => 10],
            ['id' => 3, 'lucky' => 25, 'item_id' => 6, 'qty' => 10],
            ['id' => 4, 'lucky' => 25, 'item_id' => 7, 'qty' => 10],
            ['id' => 5, 'lucky' => 50, 'item_id' => 8, 'qty' => 10],
            ['id' => 6, 'lucky' => 50, 'item_id' => 9, 'qty' => 10],
            ['id' => 7, 'lucky' => 50, 'item_id' => 71, 'qty' => 1],
            ['id' => 8, 'lucky' => 75, 'item_id' => 12928, 'qty' => 1],
            ['id' => 9, 'lucky' => 75, 'item_id' => 34252, 'qty' => 1],
            ['id' => 10, 'lucky' => 100, 'item_id' => 25187, 'qty' => 1],
        ],
    ],

];

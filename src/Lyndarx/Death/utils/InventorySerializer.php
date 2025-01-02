<?php
    
namespace Lyndarx\Death\utils;

use InvalidArgumentException;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;

final class InventorySerializer {
    private const TAG_CONTENTS = "contents";
    private const TAG_ARMOR = "armor";

    public static function serializeFromPlayer(Player $player): string {
        $contents = $player->getInventory()->getContents();
        $armorContents = $player->getArmorInventory()->getContents();
        
        return self::serialize([
            self::TAG_CONTENTS => $contents,
            self::TAG_ARMOR => $armorContents
        ]);
    }

    public static function serialize(array $contents): string {
        if(count($contents) === 0) {
            return "";
        }

        $rootTag = CompoundTag::create();

        if(isset($contents[self::TAG_CONTENTS])) {
            $contentsTag = [];
            foreach($contents[self::TAG_CONTENTS] as $slot => $item) {
                $contentsTag[] = $item->nbtSerialize($slot);
            }
            $rootTag->setTag(self::TAG_CONTENTS, new ListTag($contentsTag, NBT::TAG_Compound));
        }

        if(isset($contents[self::TAG_ARMOR])) {
            $armorTag = [];
            foreach($contents[self::TAG_ARMOR] as $slot => $item) {
                $armorTag[] = $item->nbtSerialize($slot);
            }
            $rootTag->setTag(self::TAG_ARMOR, new ListTag($armorTag, NBT::TAG_Compound));
        }

        return (new BigEndianNbtSerializer())->write(new TreeRoot($rootTag));
    }

    public static function deSerialize(string $string): array {
        if($string === "") {
            return [];
        }

        $rootTag = (new BigEndianNbtSerializer())->read($string)->mustGetCompoundTag();
        $contents = [];
        $armor = [];

        $contentsTag = $rootTag->getListTag(self::TAG_CONTENTS);
        if($contentsTag !== null) {
            foreach($contentsTag as $value) {
                try {
                    $item = Item::nbtDeserialize($value);
                    $contents[$value->getByte("Slot")] = $item;
                } catch(SavedDataLoadingException) {
                    continue;
                }
            }
        }

        $armorTag = $rootTag->getListTag(self::TAG_ARMOR);
        if($armorTag !== null) {
            foreach($armorTag as $value) {
                try {
                    $item = Item::nbtDeserialize($value);
                    $armor[$value->getByte("Slot")] = $item;
                } catch(SavedDataLoadingException) {
                    continue;
                }
            }
        }

        return [
            self::TAG_CONTENTS => $contents,
            self::TAG_ARMOR => $armor
        ];
    }
}
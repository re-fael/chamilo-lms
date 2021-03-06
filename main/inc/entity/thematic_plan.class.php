<?php

namespace Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * @license see /license.txt
 * @author autogenerated
 */
class ThematicPlan extends \CourseEntity
{
    /**
     * @return \Entity\Repository\ThematicPlanRepository
     */
     public static function repository(){
        return \Entity\Repository\ThematicPlanRepository::instance();
    }

    /**
     * @return \Entity\ThematicPlan
     */
     public static function create(){
        return new self();
    }

    /**
     * @var integer $c_id
     */
    protected $c_id;

    /**
     * @var integer $id
     */
    protected $id;

    /**
     * @var integer $thematic_id
     */
    protected $thematic_id;

    /**
     * @var string $title
     */
    protected $title;

    /**
     * @var text $description
     */
    protected $description;

    /**
     * @var integer $description_type
     */
    protected $description_type;


    /**
     * Set c_id
     *
     * @param integer $value
     * @return ThematicPlan
     */
    public function set_c_id($value)
    {
        $this->c_id = $value;
        return $this;
    }

    /**
     * Get c_id
     *
     * @return integer 
     */
    public function get_c_id()
    {
        return $this->c_id;
    }

    /**
     * Set id
     *
     * @param integer $value
     * @return ThematicPlan
     */
    public function set_id($value)
    {
        $this->id = $value;
        return $this;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Set thematic_id
     *
     * @param integer $value
     * @return ThematicPlan
     */
    public function set_thematic_id($value)
    {
        $this->thematic_id = $value;
        return $this;
    }

    /**
     * Get thematic_id
     *
     * @return integer 
     */
    public function get_thematic_id()
    {
        return $this->thematic_id;
    }

    /**
     * Set title
     *
     * @param string $value
     * @return ThematicPlan
     */
    public function set_title($value)
    {
        $this->title = $value;
        return $this;
    }

    /**
     * Get title
     *
     * @return string 
     */
    public function get_title()
    {
        return $this->title;
    }

    /**
     * Set description
     *
     * @param text $value
     * @return ThematicPlan
     */
    public function set_description($value)
    {
        $this->description = $value;
        return $this;
    }

    /**
     * Get description
     *
     * @return text 
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Set description_type
     *
     * @param integer $value
     * @return ThematicPlan
     */
    public function set_description_type($value)
    {
        $this->description_type = $value;
        return $this;
    }

    /**
     * Get description_type
     *
     * @return integer 
     */
    public function get_description_type()
    {
        return $this->description_type;
    }
}